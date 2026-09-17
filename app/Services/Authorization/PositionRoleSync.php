<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Authorization\Role;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Models\Personnel\PositionAssignment;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\TransactionRunner;
use Illuminate\Support\Facades\Schema;

/**
 * Pozisyon rolleri (D-81, 11 Eylul 2026): her pozisyonun kendi rolu vardir.
 *
 * - Pozisyon olusturulunca/adi degisince ayni adli rol uretilir ya da
 *   yeniden adlandirilir ve `positions.role_id`'ye yazilir (B03A).
 * - Pozisyona atanan personel rolu otomatik alir; atama kapanip baska acik
 *   atamasi kalmayinca rol geri alinir.
 * - Rolun yetkileri Ayarlar > Roller ekranindan isaretlenir; system_admin
 *   her izne zaten sahiptir.
 *
 * Cagiran servis transaction icindedir; syncAll() (komut/seeder) kendi
 * transaction'ini acar.
 */
final class PositionRoleSync
{
    public const GUARD = 'web';

    private ?bool $ready = null;

    public function __construct(
        private readonly TransactionRunner $transactions,
        private readonly ActivityRecorder $activities,
    ) {}

    /** Pozisyon-rol bagi kolonu var mi? Ortam degiskenine degil semaya bakar (D-89). */
    public function isReady(): bool
    {
        return $this->ready ??= Schema::hasColumn('positions', 'role_id');
    }

    /** Pozisyonun rolunu var eder (ad = pozisyon basligi) ve baglar. */
    public function ensureRoleFor(Position $position): ?Role
    {
        if (! $this->isReady()) {
            return null;
        }

        $role = $position->role_id !== null ? Role::query()->find($position->role_id) : null;
        $name = $this->roleNameFor($position, $role);

        if ($role === null) {
            $role = Role::query()
                ->where('guard_name', self::GUARD)
                ->where('name', $name)
                ->whereDoesntHave('position')
                ->first();

            if ($role === null) {
                $role = Role::query()->create(['name' => $name, 'guard_name' => self::GUARD]);
            }

            $position->forceFill(['role_id' => $role->getKey()])->saveQuietly();

            $this->activities->record(new ActivityInput(
                subjectType: 'position',
                subjectId: (int) $position->getKey(),
                actionCode: 'position.role_synced',
                changes: ['rol' => $role->name],
            ));
        } elseif ($role->name !== $name) {
            $previous = $role->name;
            $role->forceFill(['name' => $name])->save();

            $this->activities->record(new ActivityInput(
                subjectType: 'position',
                subjectId: (int) $position->getKey(),
                actionCode: 'position.role_synced',
                changes: ['rol' => ['onceki' => $previous, 'yeni' => $name]],
            ));
        }

        return $role;
    }

    /** Acik atamasi olan herkese pozisyon rolunu verir; verilen kisi sayisini doner. */
    public function syncHolders(Position $position): int
    {
        $role = $this->ensureRoleFor($position);

        if ($role === null) {
            return 0;
        }

        $count = 0;

        $position->assignments()
            ->whereNull('valid_until')
            ->with('personnel')
            ->get()
            ->each(function (PositionAssignment $assignment) use ($role, &$count): void {
                $personnel = $assignment->personnel;

                if ($personnel instanceof Personnel && ! $personnel->hasRole($role)) {
                    $this->grant($personnel, $role);
                    $count++;
                }
            });

        return $count;
    }

    /** Atama acildiginda: personel rolu alir. */
    public function assign(PositionAssignment $assignment): void
    {
        if (! $this->isReady() || $assignment->valid_until !== null) {
            return;
        }

        $position = $assignment->position;
        $personnel = $assignment->personnel;

        if (! $position instanceof Position || ! $personnel instanceof Personnel) {
            return;
        }

        $role = $this->ensureRoleFor($position);

        if ($role !== null && ! $personnel->hasRole($role)) {
            $this->grant($personnel, $role);
        }
    }

    /** Atama kapaninca/silinince: ayni pozisyonda baska acik atama yoksa rol alinir. */
    public function revoke(PositionAssignment $assignment): void
    {
        if (! $this->isReady()) {
            return;
        }

        $position = $assignment->position;
        $personnel = $assignment->personnel;

        if (! $position instanceof Position || ! $personnel instanceof Personnel || $position->role_id === null) {
            return;
        }

        $stillHolds = PositionAssignment::query()
            ->where('personnel_id', $personnel->getKey())
            ->where('position_id', $position->getKey())
            ->whereKeyNot($assignment->getKey())
            ->whereNull('valid_until')
            ->exists();

        if ($stillHolds) {
            return;
        }

        $role = Role::query()->find($position->role_id);

        if ($role !== null && $personnel->hasRole($role)) {
            $personnel->removeRole($role);

            $this->activities->record(new ActivityInput(
                subjectType: 'personnel',
                subjectId: (int) $personnel->getKey(),
                actionCode: 'personnel.role_revoked',
                changes: ['rol' => $role->name, 'pozisyon' => $position->title],
            ));
        }
    }

    /**
     * Tum pozisyonlar icin rol uretir ve acik atama sahiplerine verir
     * (konsol komutu ve RoleSeeder).
     *
     * @return array{positions: int, granted: int}
     */
    public function syncAll(): array
    {
        if (! $this->isReady()) {
            return ['positions' => 0, 'granted' => 0];
        }

        return $this->transactions->run(function (): array {
            $positions = 0;
            $granted = 0;

            Position::query()->with('orgUnit')->orderBy('id')->get()->each(function (Position $position) use (&$positions, &$granted): void {
                $positions++;
                $granted += $this->syncHolders($position);
            });

            return ['positions' => $positions, 'granted' => $granted];
        });
    }

    private function grant(Personnel $personnel, Role $role): void
    {
        $personnel->assignRole($role);

        $this->activities->record(new ActivityInput(
            subjectType: 'personnel',
            subjectId: (int) $personnel->getKey(),
            actionCode: 'personnel.role_assigned',
            changes: ['rol' => $role->name],
        ));
    }

    /**
     * Rol adi = pozisyon basligi. Ayni ad baska bir rolde (system_admin,
     * baska birimin ayni adli pozisyonu) kullaniliyorsa birim kodu eklenir.
     */
    private function roleNameFor(Position $position, ?Role $current): string
    {
        $title = trim((string) $position->title);
        $taken = Role::query()
            ->where('guard_name', self::GUARD)
            ->where('name', $title)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->getKey()))
            ->exists();

        if (! $taken) {
            return $title;
        }

        $unit = $position->orgUnit?->code ?? $position->code;

        return sprintf('%s (%s)', $title, $unit);
    }
}
