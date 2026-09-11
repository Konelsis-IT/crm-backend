<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Services\Audit\ActivityRecorder;
use App\Services\Authorization\PositionRoleSync;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Exceptions\DuplicateRecordException;
use App\Models\Personnel\PositionAssignment;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Personel-pozisyon atama servisi.
 *
 * create ve update override edilmistir: bir personelin ayni anda (acik,
 * is_primary=true) birden fazla asil pozisyonu olamaz (primary_active_guard
 * ile veritabaninda da korunur; burada kullaniciya net mesaj icin onceden
 * kontrol edilir).
 */
final class PositionAssignmentService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['position'];

    protected string $orderBy = 'valid_from';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly PositionRoleSync $roles,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        if ((bool) ($data['is_primary'] ?? false)) {
            $this->assertNoActivePrimary((int) ($data['personnel_id'] ?? 0), null);
        }

        return $this->transactions->run(function () use ($data): Model {
            /** @var PositionAssignment $assignment */
            $assignment = parent::create([
                ...$data,
                'valid_from' => $data['valid_from'] ?? Carbon::now('UTC')->toDateString(),
            ]);

            // Pozisyon sahibi pozisyonun rolunu alir (D-81).
            $this->roles->assign($assignment);

            return $assignment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $current = $this->show($record);

        $isPrimary = array_key_exists('is_primary', $data) ? (bool) $data['is_primary'] : (bool) $current->is_primary;
        $isOpen = array_key_exists('valid_until', $data) ? blank($data['valid_until']) : $current->valid_until === null;

        if ($isPrimary && $isOpen) {
            $this->assertNoActivePrimary((int) $current->personnel_id, $current);
        }

        return $this->transactions->run(function () use ($current, $data): Model {
            /** @var PositionAssignment $assignment */
            $assignment = parent::update($current, $data);

            // Atama kapandiysa rol geri alinir, acik kaldiysa verilir (D-81).
            $assignment->valid_until === null
                ? $this->roles->assign($assignment)
                : $this->roles->revoke($assignment);

            return $assignment;
        });
    }

    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var PositionAssignment $assignment */
            $assignment = $this->show($record);
            $this->roles->revoke($assignment);

            return parent::delete($assignment);
        });
    }

    private function assertNoActivePrimary(int $personnelId, ?Model $except): void
    {
        $query = PositionAssignment::query()
            ->where('personnel_id', $personnelId)
            ->where('is_primary', true)
            ->whereNull('valid_until');

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw DuplicateRecordException::make();
        }
    }
}
