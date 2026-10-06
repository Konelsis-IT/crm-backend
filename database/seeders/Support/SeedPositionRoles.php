<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Models\Authorization\Role;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Models\Personnel\PositionAssignment;
use App\Services\Authorization\PositionRoleSync;

/**
 * Seed icin pozisyon rolleri (D-165). PositionRoleSync::syncAll() her
 * calismada acik atamasi olan herkese pozisyon rolunu yeniden verir; canlida
 * birinden alinan rol boylece geri geliyordu ("personelin yetkisi
 * bozulmus"). Seed'ler syncAll() cagirmaz, bunu kullanir:
 *
 * - Rol yalniz bu calismada seed ile eklenen pozisyona uretilir.
 * - Rol yalniz bu calismada seed ile acilan goreve verilir.
 *
 * Canlida var olan pozisyon, rol ve atamaya dokunulmaz.
 */
final class SeedPositionRoles
{
    /**
     * @return array{positions: int, granted: int}
     */
    public static function sync(): array
    {
        $sync = app(PositionRoleSync::class);

        if (! $sync->isReady()) {
            return ['positions' => 0, 'granted' => 0];
        }

        $positions = 0;
        $granted = 0;

        foreach (Position::query()->orderBy('id')->get() as $position) {
            if (SeedGuard::createdInProcess($position)) {
                $positions++;
                $granted += $sync->syncHolders($position);
            }
        }

        $assignments = PositionAssignment::query()->whereNull('valid_until')->with(['position', 'personnel'])->orderBy('id')->get();

        foreach ($assignments as $assignment) {
            $position = $assignment->position;
            $personnel = $assignment->personnel;

            // Yeni pozisyonun sahipleri yukarida rolu aldi; burada yalniz var olan
            // pozisyona seed ile acilan yeni gorev.
            if (! SeedGuard::createdInProcess($assignment) || ! $position instanceof Position || SeedGuard::createdInProcess($position) || ! $personnel instanceof Personnel) {
                continue;
            }

            $role = $position->role_id !== null ? Role::query()->find($position->role_id) : null;

            if ($role !== null && ! $personnel->hasRole($role)) {
                $personnel->assignRole($role);
                $granted++;
            }
        }

        return ['positions' => $positions, 'granted' => $granted];
    }
}
