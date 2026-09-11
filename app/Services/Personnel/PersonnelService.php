<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Enums\Personnel\PersonnelStatus;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Personnel\CircularManagerChainException;
use App\Exceptions\Personnel\EmailAlreadyInUseException;
use App\Models\Personnel\Personnel;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Personel servisi.
 *
 * index, show ve delete AbstractService'ten gelir. create ve update
 * override edilmistir, cunku personelde iki ek kural vardir: e-posta
 * tekilligi ve yetkinlik listesinin senkronu.
 *
 * changeStatus ise bes temel islemin disinda, personele ozel bir istir.
 */
final class PersonnelService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['orgUnit', 'competencies'];

    protected string $orderBy = 'full_name';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly SyncPersonnelCompetencies $competencies,
        private readonly SyncPersonnelAssignment $assignments,
        private readonly SyncPersonnelManager $managers,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $this->assertEmailIsFree((string) ($data['email'] ?? ''), null);

            /** @var Personnel $personnel */
            $personnel = parent::create($data);

            $this->syncCompetencies($personnel, $data);
            $this->syncRoles($personnel, $data);

            return $personnel;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var Personnel $current */
            $current = $this->show($record);
            $this->assertEmailIsFree((string) ($data['email'] ?? ''), $current);

            $managerProvided = array_key_exists('direct_manager_personnel_id', $data);
            $managerId = $this->extractManagerId($data);

            if ($managerProvided && $managerId !== null) {
                $this->assertNoManagerCycle((int) $current->getKey(), $managerId);
            }

            $before = clone $current;

            /** @var Personnel $personnel */
            $personnel = parent::update($current, $data);

            $this->syncCompetencies($personnel, $data);
            $this->syncRoles($personnel, $data);
            $this->assignments->syncIfChanged($personnel, $before);

            if ($managerProvided) {
                $change = $this->managers->syncIfChanged($personnel, $managerId);

                if ($change !== null) {
                    $this->recordActivity($personnel, 'manager_changed', ['dogrudan_amir' => $change]);
                }
            }

            return $personnel;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractManagerId(array &$data): ?int
    {
        $managerId = $data['direct_manager_personnel_id'] ?? null;
        unset($data['direct_manager_personnel_id']);

        return $managerId !== null && $managerId !== '' ? (int) $managerId : null;
    }

    /**
     * Personel durumunu degistirir. Durum formdan duzenlenmez; yalniz bu
     * islem uzerinden ve enumda tanimli izinli gecislere gore degisir.
     */
    public function changeStatus(Model|int|string $record, PersonnelStatus $target, ?string $reason = null): Personnel
    {
        return $this->transactions->run(function () use ($record, $target, $reason): Personnel {
            /** @var Personnel $personnel */
            $personnel = $this->lockForUpdate($record);
            $from = $personnel->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make([
                    'from' => $from->getLabel(),
                    'to' => $target->getLabel(),
                ]);
            }

            $personnel->forceFill([
                'status' => $target,
                'failed_login_count' => $target === PersonnelStatus::Active ? 0 : $personnel->failed_login_count,
            ])->save();

            $this->recordActivity($personnel, 'status_changed', [
                'durum' => ['onceki' => $from->value, 'yeni' => $target->value],
                'gerekce' => $reason,
            ]);

            return $personnel;
        });
    }

    /**
     * Parola bos gelirse degistirilmez; doluysa degisim zamani da yazilir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $data = parent::prepare($data, $record);

        unset($data['competencyRecords'], $data['roles']);

        foreach (['full_name', 'email', 'national_id', 'phone', 'job_title'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password_changed_at'] = Carbon::now('UTC');
        }

        return $data;
    }

    /** Ayni e-postayi baska bir personel kullaniyor mu? */
    private function assertEmailIsFree(string $email, ?Personnel $current): void
    {
        $normalized = Personnel::normalizeEmail($email);

        if ($normalized === null) {
            throw EmailAlreadyInUseException::make(['email' => $email]);
        }

        $query = Personnel::query()->where('normalized_email', $normalized);

        if ($current !== null) {
            $query->whereKeyNot($current->getKey());
        }

        if ($query->exists()) {
            throw EmailAlreadyInUseException::make(['email' => $email]);
        }
    }

    /**
     * Formdan yetkinlik listesi geldiyse esitler ve degisikligi hareket
     * kaydina ek satir olarak yazar.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncCompetencies(Personnel $personnel, array $data): void
    {
        if (! isset($data['competencyRecords']) || ! is_array($data['competencyRecords'])) {
            return;
        }

        $changes = $this->competencies->sync($personnel, $data['competencyRecords']);

        if ($changes !== []) {
            $this->recordActivity($personnel, 'competencies_changed', $changes);
        }
    }

    /**
     * Formdan rol listesi geldiyse esitler (spatie/laravel-permission) ve
     * degisikligi hareket kaydina ek satir olarak yazar.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncRoles(Personnel $personnel, array $data): void
    {
        if (! array_key_exists('roles', $data) || ! is_array($data['roles'])) {
            return;
        }

        $before = $personnel->roles->pluck('name')->sort()->values()->all();

        $personnel->syncRoles(array_map('intval', $data['roles']));

        $after = $personnel->roles()->get()->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            $this->recordActivity($personnel, 'roles_changed', [
                'roller' => ['onceki' => implode(', ', $before) ?: '-', 'yeni' => implode(', ', $after) ?: '-'],
            ]);
        }
    }

    /**
     * Bir personel, dogrudan veya zincirleme olarak kendi altindaki birine
     * raporlayamaz.
     */
    private function assertNoManagerCycle(int $personnelId, int $candidateManagerId): void
    {
        if ($candidateManagerId === $personnelId) {
            throw CircularManagerChainException::make();
        }

        $current = Personnel::query()->find($candidateManagerId);
        $depth = 0;

        while ($current !== null && $depth < 50) {
            $manager = $current->currentManager();

            if ($manager !== null && (int) $manager->getKey() === $personnelId) {
                throw CircularManagerChainException::make();
            }

            $current = $manager;
            $depth++;
        }
    }
}
