<?php

declare(strict_types=1);

namespace App\Query\Party;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Shared\ActiveStatus;
use App\Models\Party\ActivityArea;
use Illuminate\Database\Eloquent\Builder;

/**
 * Faaliyet alani secenekleri ve taraf listesi faaliyet suzgeci (B33, D-107).
 */
final class ActivityAreaQueries
{
    /**
     * Ana faaliyet alanlari (id => ad). Pasif alanlar yeni secime kapalidir;
     * $keep ile verilen (kayitta secili) alan pasif olsa da listede kalir.
     * $includeInactive: Ayarlar ve suzgec icin tum alanlar.
     *
     * @return array<int, string>
     */
    public function rootOptions(bool $includeInactive = false, ?int $keep = null): array
    {
        return $this->ordered(ActivityArea::query()->whereNull('parent_id'), $includeInactive, $keep);
    }

    /**
     * Verilen ana alanin alt faaliyet alanlari (id => ad); ana alan yoksa bos.
     *
     * @return array<int, string>
     */
    public function childOptions(?int $parentId, bool $includeInactive = false, ?int $keep = null): array
    {
        if ($parentId === null || $parentId <= 0) {
            return [];
        }

        return $this->ordered(ActivityArea::query()->where('parent_id', $parentId), $includeInactive, $keep);
    }

    /**
     * Tum alt faaliyet alanlari "Ana › Alt" etiketiyle (suzgecte ana alan
     * secilmeden de aranabilsin diye).
     *
     * @return array<int, string>
     */
    public function allChildOptions(): array
    {
        return ActivityArea::query()
            ->whereNotNull('parent_id')
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name_tr')
            ->get()
            ->sortBy(fn (ActivityArea $area): string => sprintf('%05d|%s|%05d', (int) $area->parent?->sort_order, (string) $area->parent?->name_tr, (int) $area->sort_order))
            ->mapWithKeys(fn (ActivityArea $area): array => [(int) $area->getKey() => ($area->parent?->name_tr ?? '-').' › '.$area->name_tr])
            ->all();
    }

    /**
     * Ayarlar listesi sirasi: her ana alan, hemen altinda kendi alt alanlari.
     * Birlesim (join) kullanilmaz; arama kolonlari belirsiz kalmasin diye ust
     * alanin sirasi ve adi alt sorguyla okunur.
     */
    public function catalogOrder(Builder $query): Builder
    {
        $parent = fn (string $column) => ActivityArea::query()
            ->from('activity_areas as area_root')
            ->select("area_root.{$column}")
            ->whereColumn('area_root.id', 'activity_areas.parent_id');

        return $query
            ->with('parent')
            ->orderByRaw('COALESCE(('.$parent('sort_order')->toSql().'), activity_areas.sort_order)')
            ->orderByRaw('COALESCE(('.$parent('name_tr')->toSql().'), activity_areas.name_tr)')
            ->orderByRaw('activity_areas.parent_id IS NOT NULL')
            ->orderBy('activity_areas.sort_order')
            ->orderBy('activity_areas.name_tr');
    }

    /** Ayarlar suzgeci: secilen ana alan ve onun alt alanlari. */
    public function withinRoot(Builder $query, ?int $rootId): Builder
    {
        if ($rootId === null || $rootId <= 0) {
            return $query;
        }

        return $query->where(fn (Builder $inner): Builder => $inner
            ->where('activity_areas.id', $rootId)
            ->orWhere('activity_areas.parent_id', $rootId));
    }

    /**
     * Faaliyet suzgeci: secilen kosullarin HEPSI ayni faaliyet satirinda
     * saglanmalidir. Proje tipi secildiginde "tum proje tipleri" satirlari da
     * eslesir (or. her tipte calisan CED firmasi HES aramasinda gorunur).
     */
    public function applyFilter(Builder $query, ?string $projectType, ?int $areaId, ?int $subId): Builder
    {
        if (blank($projectType) && ($areaId ?? 0) <= 0 && ($subId ?? 0) <= 0) {
            return $query;
        }

        return $query->whereHas('activityAreas', function (Builder $rows) use ($projectType, $areaId, $subId): void {
            if (filled($projectType)) {
                $rows->where(fn (Builder $type): Builder => $type->where('project_type', $projectType)->orWhereNull('project_type'));
            }

            if (($areaId ?? 0) > 0) {
                $rows->where('activity_area_id', $areaId);
            }

            if (($subId ?? 0) > 0) {
                $rows->where('sub_activity_area_id', $subId);
            }
        });
    }

    /**
     * Suzgec rozetleri: "Proje tipi: HES", "Faaliyet alani: ...".
     *
     * @return list<string>
     */
    public function indicators(?ProjectScopeType $projectType, ?int $areaId, ?int $subId): array
    {
        $names = ActivityArea::query()->whereIn('id', array_filter([$areaId, $subId]))->pluck('name_tr', 'id');

        return array_values(array_filter([
            $projectType !== null ? __('party.fields.project_type').': '.$projectType->getLabel() : null,
            $areaId !== null && isset($names[$areaId]) ? __('party.fields.activity_area').': '.$names[$areaId] : null,
            $subId !== null && isset($names[$subId]) ? __('party.fields.sub_activity_area').': '.$names[$subId] : null,
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function ordered(Builder $query, bool $includeInactive, ?int $keep = null): array
    {
        if (! $includeInactive) {
            $query->where(fn (Builder $inner): Builder => $inner
                ->where('status', ActiveStatus::Active->value)
                ->when($keep !== null, fn (Builder $kept): Builder => $kept->orWhere('id', $keep)));
        }

        return $query->orderBy('sort_order')->orderBy('name_tr')->pluck('name_tr', 'id')->all();
    }
}
