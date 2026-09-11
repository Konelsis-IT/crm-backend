<?php

declare(strict_types=1);

namespace App\Query\Notification;

use App\Models\Authorization\Role;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\ReportingRelationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bildirim hedef kitlesi sorgulari (D-82). Yalniz aktif personel doner.
 */
final class AudienceQueries
{
    /** Amirin ekibi: dogrudan raporlayanlar + yonettigi birimlerin personeli. */
    public function team(int $managerId): Collection
    {
        $directIds = ReportingRelationship::query()
            ->where('manager_personnel_id', $managerId)
            ->where('relation_type', 'line')
            ->whereNull('valid_until')
            ->pluck('personnel_id');

        $unitIds = OrgUnit::query()
            ->where('manager_personnel_id', $managerId)
            ->pluck('id');

        return $this->active()
            ->where(function (Builder $query) use ($directIds, $unitIds): void {
                $query->whereIn('id', $directIds)->orWhereIn('org_unit_id', $unitIds);
            })
            ->get();
    }

    public function department(int $orgUnitId): Collection
    {
        return $this->active()->where('org_unit_id', $orgUnitId)->get();
    }

    public function role(int $roleId): Collection
    {
        return $this->active()
            ->whereHas('roles', fn (Builder $query) => $query->whereKey($roleId))
            ->get();
    }

    /**
     * @param  list<int>  $ids
     */
    public function personnel(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return $this->active()->whereIn('id', $ids)->get();
    }

    public function all(): Collection
    {
        return $this->active()->get();
    }

    /**
     * @param  list<int>  $ids
     */
    public function byIds(array $ids): Collection
    {
        return $this->personnel($ids);
    }

    /** Tum system_admin rolu sahipleri (sahipsiz uyarilarin yedek alicisi). */
    public function systemAdmins(): Collection
    {
        return Personnel::query()->where('status', 'active')->role('system_admin')->get();
    }

    public function orgUnitLabel(int $orgUnitId): ?string
    {
        return OrgUnit::query()->whereKey($orgUnitId)->value('name');
    }

    public function roleLabel(int $roleId): ?string
    {
        return Role::query()->whereKey($roleId)->value('name');
    }

    /** Ulasilabilir personel (aktif, davet edilmis, izinde). */
    private function active(): Builder
    {
        return Personnel::query()
            ->reachable()
            ->orderBy('full_name');
    }
}
