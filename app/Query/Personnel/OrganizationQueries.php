<?php

declare(strict_types=1);

namespace App\Query\Personnel;

use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PositionAssignment;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;

/**
 * Organizasyon ekranlarinin okuma sorgulari.
 *
 * Departman uyeligi (D-218; kullanici 15 Ekim 2026: "Yusuf Bey teklif grup
 * müdürü olduğu için hem iş geliştirme hem teklif departmanında çalışan bir
 * personeldir. Mukaddes ve Haydar bey'de hem teklif hem iş geliştirme
 * departmanında çalışan personellerdir."): bir kisi kendi departmaninin yaninda
 * acik gorev atamalarinin (ek gorev, is_primary = 0) departmanlarinda da
 * calisir. Departmana giden talep, uyari, duyuru, ekip ve pano bu uyeligi okur.
 */
final class OrganizationQueries
{
    /** Istek suresince koda gore birim. @var array<string, OrgUnit|null> */
    private array $byCode = [];

    /** Istek suresince kisinin departmanlari. @var array<int, list<int>> */
    private array $unitsOf = [];

    /**
     * Ust birim secim listesi; verilen id kendisiyle secilemesin diye disarida birakilir.
     *
     * @return array<int, string>
     */
    public function orgUnitOptions(?int $excludingId = null): array
    {
        return OrgUnit::query()
            ->when($excludingId !== null, fn ($query) => $query->whereKeyNot($excludingId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function unit(int $id): ?OrgUnit
    {
        return $id > 0 ? OrgUnit::query()->find($id) : null;
    }

    /** Koduyla birim (SATIN_ALMA, IS_GELISTIRME...); yoksa null. */
    public function unitByCode(string $code): ?OrgUnit
    {
        if (! array_key_exists($code, $this->byCode)) {
            $this->byCode[$code] = OrgUnit::query()->with('manager')->where('code', $code)->first();
        }

        return $this->byCode[$code];
    }

    /**
     * Personel sorgusunu verilen departmanlarda calisanlara daraltir: kendi
     * departmani ya da acik gorev atamasinin departmani (D-218).
     *
     * @param  list<int>  $unitIds
     */
    public function inUnits(Builder $personnelQuery, array $unitIds): Builder
    {
        $unitIds = array_values(array_unique(array_filter(array_map('intval', $unitIds))));

        if ($unitIds === []) {
            return $personnelQuery->whereRaw('1 = 0');
        }

        $column = $personnelQuery->getModel()->qualifyColumn('org_unit_id');

        return $personnelQuery->where(function (Builder $any) use ($column, $unitIds): void {
            $any->whereIn($column, $unitIds);

            if (SchemaReadiness::hasBatch('B03')) {
                $any->orWhereHas('positionAssignments', fn (Builder $assignment): Builder => $assignment
                    ->whereNull('valid_until')
                    ->whereHas('position', fn (Builder $position): Builder => $position->whereIn('org_unit_id', $unitIds)));
            }
        });
    }

    /**
     * Kisinin calistigi departmanlar: kendi departmani once, sonra ek gorevlerinin
     * departmanlari (D-218).
     *
     * @return list<int>
     */
    public function unitIdsOf(int $personnelId): array
    {
        if ($personnelId <= 0) {
            return [];
        }

        if (isset($this->unitsOf[$personnelId])) {
            return $this->unitsOf[$personnelId];
        }

        $ids = [];
        $own = Personnel::query()->whereKey($personnelId)->value('org_unit_id');

        if ($own !== null) {
            $ids[] = (int) $own;
        }

        if (SchemaReadiness::hasBatch('B03')) {
            $extra = PositionAssignment::query()
                ->join('positions', 'positions.id', '=', 'position_assignments.position_id')
                ->where('position_assignments.personnel_id', $personnelId)
                ->whereNull('position_assignments.valid_until')
                ->orderByDesc('position_assignments.is_primary')
                ->orderBy('position_assignments.id')
                ->pluck('positions.org_unit_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $ids = [...$ids, ...$extra];
        }

        return $this->unitsOf[$personnelId] = array_values(array_unique(array_filter($ids)));
    }

    /**
     * Kisinin departman kodlari (IS_GELISTIRME, TEKLIF...), kendi departmani once.
     *
     * @return list<string>
     */
    public function unitCodesOf(int $personnelId): array
    {
        $ids = $this->unitIdsOf($personnelId);

        if ($ids === []) {
            return [];
        }

        $codes = OrgUnit::query()->whereIn('id', $ids)->pluck('code', 'id');

        return array_values(array_filter(array_map(fn (int $id): ?string => $codes[$id] ?? null, $ids)));
    }

    /**
     * Herkesin acik gorev atamalarinin departman kodlari, tek sorguda (is panosu
     * gibi toplu ekranlar icin; D-218).
     *
     * @return array<int, list<string>> personel kimligi => departman kodlari
     */
    public function assignmentUnitCodes(): array
    {
        if (! SchemaReadiness::hasBatch('B03')) {
            return [];
        }

        $codes = [];

        PositionAssignment::query()
            ->join('positions', 'positions.id', '=', 'position_assignments.position_id')
            ->join('org_units', 'org_units.id', '=', 'positions.org_unit_id')
            ->whereNull('position_assignments.valid_until')
            ->orderByDesc('position_assignments.is_primary')
            ->orderBy('position_assignments.id')
            ->get(['position_assignments.personnel_id', 'org_units.code'])
            ->each(function (PositionAssignment $row) use (&$codes): void {
                $codes[(int) $row->getAttribute('personnel_id')][] = (string) $row->getAttribute('code');
            });

        return $codes;
    }

    /**
     * Kisinin ek departmanlarinin adlari (kendi departmani haric; liste ve kart icin).
     *
     * @return list<string>
     */
    public function extraUnitNamesOf(int $personnelId): array
    {
        $ids = array_slice($this->unitIdsOf($personnelId), 1);

        if ($ids === []) {
            return [];
        }

        $names = OrgUnit::query()->whereIn('id', $ids)->pluck('name', 'id');

        return array_values(array_filter(array_map(fn (int $id): ?string => $names[$id] ?? null, $ids)));
    }
}
