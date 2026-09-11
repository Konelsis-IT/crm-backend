<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Personnel;
use App\Models\Personnel\ReportingRelationship;
use Illuminate\Support\Carbon;

/**
 * "Dogrudan Amir" secimi degistiginde reporting_relationships'te
 * (relation_type=line, scope_type=all) tarihceli bir kayit acar/kapatir
 * (personnel_assignments'taki desenin ayni). Ekranda ayri bir form
 * yoktur; yalniz PersonnelService::update() cagirir.
 */
final class SyncPersonnelManager
{
    /**
     * @return array{onceki: ?string, yeni: ?string}|null Degisiklik ozeti; degisiklik yoksa null.
     */
    public function syncIfChanged(Personnel $personnel, ?int $managerId): ?array
    {
        $current = $personnel->currentManager();
        $currentId = $current?->getKey();

        if ($currentId === $managerId) {
            return null;
        }

        $today = Carbon::now('UTC')->toDateString();

        $open = ReportingRelationship::query()
            ->where('personnel_id', $personnel->getKey())
            ->where('relation_type', 'line')
            ->whereNull('valid_until')
            ->first();

        if ($open !== null && $open->valid_from->toDateString() === $today) {
            if ($managerId === null) {
                $open->delete();
            } else {
                $open->forceFill(['manager_personnel_id' => $managerId])->save();
            }
        } else {
            if ($open !== null) {
                $open->forceFill(['valid_until' => $today])->save();
            }

            if ($managerId !== null) {
                ReportingRelationship::query()->create([
                    'personnel_id' => $personnel->getKey(),
                    'manager_personnel_id' => $managerId,
                    'relation_type' => 'line',
                    'scope_type' => 'all',
                    'valid_from' => $today,
                    'valid_until' => null,
                ]);
            }
        }

        $newManager = $managerId !== null ? Personnel::query()->whereKey($managerId)->value('full_name') : null;

        return ['onceki' => $current?->full_name, 'yeni' => $newManager];
    }
}
