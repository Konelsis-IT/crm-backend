<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\OrgUnitRelation;
use Illuminate\Support\Carbon;

/**
 * Bir organizasyon biriminin ust birimi degistiginde tarihceli bir kayit
 * acar/kapatir (personnel_assignments'taki desenin ayni). Ekranda ayri
 * bir form yoktur; yalniz OrgUnitService::update() cagirir.
 */
final class SyncOrgUnitParent
{
    /**
     * @return array{onceki: ?string, yeni: ?string}|null Degisiklik ozeti; degisiklik yoksa null.
     */
    public function syncIfChanged(OrgUnit $unit, ?int $parentId): ?array
    {
        $current = $unit->currentParent();
        $currentId = $current?->getKey();

        if ($currentId === $parentId) {
            return null;
        }

        $today = Carbon::now('UTC')->toDateString();

        $open = OrgUnitRelation::query()
            ->where('child_org_unit_id', $unit->getKey())
            ->where('relation_type', 'hierarchy')
            ->whereNull('valid_until')
            ->first();

        if ($open !== null && $open->valid_from->toDateString() === $today) {
            if ($parentId === null) {
                $open->delete();
            } else {
                $open->forceFill(['parent_org_unit_id' => $parentId])->save();
            }
        } else {
            if ($open !== null) {
                $open->forceFill(['valid_until' => $today])->save();
            }

            if ($parentId !== null) {
                OrgUnitRelation::query()->create([
                    'parent_org_unit_id' => $parentId,
                    'child_org_unit_id' => $unit->getKey(),
                    'relation_type' => 'hierarchy',
                    'valid_from' => $today,
                    'valid_until' => null,
                ]);
            }
        }

        $newParent = $parentId !== null ? OrgUnit::query()->whereKey($parentId)->value('name') : null;

        return ['onceki' => $current?->name, 'yeni' => $newParent];
    }
}
