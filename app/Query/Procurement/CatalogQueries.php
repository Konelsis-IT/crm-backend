<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\UsageArea;
use App\Filament\Support\TwoLineOption;
use App\Models\Procurement\CatalogCategory;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CostListLine;
use App\Services\Platform\SchemaReadiness;
use App\Support\Procurement\CatalogNames;
use Illuminate\Database\Eloquent\Builder;

/**
 * Katalog okumalari (B52, D-189): secim kutulari icin arama, kategori
 * secenekleri, kalemi isteyen teklif / proje satirlari, arsiv suzgeci.
 */
final class CatalogQueries
{
    /**
     * Arama: ad ya da esleme adi (Turkce harf farki gozetilmez). Anahtar kimlik,
     * deger "Ad · Kategori".
     *
     * @param  list<CatalogItemKind>|null  $kinds
     * @return array<int, string>
     */
    public function itemOptions(string $search = '', ?array $kinds = null, ?int $only = null, int $limit = 50, ?UsageArea $usage = null): array
    {
        $options = [];

        foreach ($this->itemRows($search, $kinds, $only, $limit, $usage) as $id => [$name, $path]) {
            $options[$id] = $name.($path !== null ? ' · '.$path : '');
        }

        return $options;
    }

    /**
     * Iki satirli secim (D-192, kullanici: "Inputta 2 satir halinde urun adi
     * altinda aciklama yazisi gibi kategorisi yazilir"): ayni arama, deger
     * TwoLineOption HTML'i (ust satir ad, alt satir silik kategori yolu).
     * Select'te `->allowHtml()` ve `getSearchResultsUsing` ile kullanilir;
     * arama veritabaninda duz metindedir.
     *
     * @param  list<CatalogItemKind>|null  $kinds
     * @return array<int, string>
     */
    public function itemOptionsHtml(string $search = '', ?array $kinds = null, ?int $only = null, int $limit = 50, ?UsageArea $usage = null): array
    {
        return TwoLineOption::options($this->itemRows($search, $kinds, $only, $limit, $usage));
    }

    /** Secili kalemin iki satirli etiketi (arsivli kalem de; `getOptionLabelUsing`). */
    public function itemLabelHtml(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $row = $this->itemRowsFor([$id])[(int) $id] ?? null;

        return $row !== null ? TwoLineOption::html($row[0], $row[1]) : null;
    }

    /**
     * Adi (ya da esleme adi) aynen tutan arsivsiz kalem (yapistirilan
     * satirlar icin; benzer ad tahmin edilmez).
     */
    public function exactItemId(string $name): ?int
    {
        $needle = CatalogNames::normalize($name);

        if ($needle === '') {
            return null;
        }

        $id = CatalogItem::query()->notArchived()
            ->where(fn (Builder $match): Builder => $match
                ->where('normalized_name', $needle)
                ->orWhereHas('aliases', fn (Builder $alias): Builder => $alias->where('normalized_alias', $needle)))
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** Kalemin birimi (secilince talep / teklif satirina yazilir). */
    public function itemUnit(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $unit = CatalogItem::query()->whereKey((int) $id)->value('unit');

        return filled($unit) ? (string) $unit : null;
    }

    /**
     * Coklu secimin iki satirli etiketleri (`getOptionLabelsUsing`).
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, string>
     */
    public function itemLabelsHtml(array $ids): array
    {
        return TwoLineOption::options($this->itemRowsFor($ids));
    }

    /**
     * Verilen kalemlerin [ad, kategori yolu] satirlari (arsivli kalem de).
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function itemRowsFor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $rows = [];

        foreach (CatalogItem::query()->with('category.parent')->whereKey($ids)->get() as $item) {
            /** @var CatalogItem $item */
            $category = $item->category;
            $rows[(int) $item->getKey()] = [(string) $item->getAttribute('name'), $category instanceof CatalogCategory ? $category->path() : null];
        }

        return $rows;
    }

    /**
     * Arama: ad ya da esleme adi (Turkce harf farki gozetilmez); kimlik =>
     * [ad, kategori yolu].
     *
     * @param  list<CatalogItemKind>|null  $kinds
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function itemRows(string $search = '', ?array $kinds = null, ?int $only = null, int $limit = 50, ?UsageArea $usage = null): array
    {
        $query = CatalogItem::query()->notArchived()->with('category.parent')->orderBy('name')->limit($limit);

        if ($only !== null) {
            $query->whereKey($only);
        }

        if ($usage !== null && SchemaReadiness::hasBatch('B52A')) {
            $query->where('usage_area', $usage->value);
        }

        if ($kinds !== null) {
            $query->whereIn('kind', array_map(static fn (CatalogItemKind $kind): string => $kind->value, $kinds));
        }

        $needle = CatalogNames::normalize($search);

        if ($needle !== '') {
            $query->where(fn (Builder $match): Builder => $match
                ->where('normalized_name', 'like', '%'.$needle.'%')
                ->orWhereHas('aliases', fn (Builder $alias): Builder => $alias->where('normalized_alias', 'like', '%'.$needle.'%')));
        }

        $rows = [];

        foreach ($query->get() as $item) {
            /** @var CatalogItem $item */
            $category = $item->category;
            $rows[(int) $item->getKey()] = [(string) $item->getAttribute('name'), $category instanceof CatalogCategory ? $category->path() : null];
        }

        return $rows;
    }

    /**
     * Turun kategori secenekleri ("Insaat › Saha Duzeltme").
     *
     * @return array<int, string>
     */
    public function categoryOptions(?CatalogItemKind $kind = null): array
    {
        $query = CatalogCategory::query()->notArchived()->with('parent')->orderBy('sort_order')->orderBy('name');

        if ($kind !== null) {
            $tree = $kind === CatalogItemKind::Service ? CatalogItemKind::Product : $kind;
            $query->where('kind', $tree->value);
        }

        return $query->get()
            ->mapWithKeys(fn (CatalogCategory $category): array => [(int) $category->getKey() => $category->path()])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
    }

    /** Kalemi isteyen teklif maliyet satirlari (teklif ve proje baglantisiyla). */
    public function costLinesFor(int $catalogItemId): Builder
    {
        return CostListLine::query()
            ->where('catalog_item_id', $catalogItemId)
            ->with(['costList.proposal.businessCase', 'purchaseNeed.project'])
            ->latest('id');
    }

    public function archiveScope(Builder $query, string $mode): Builder
    {
        if (! SchemaReadiness::hasBatch('B52')) {
            return $query;
        }

        return match ($mode) {
            'archived' => $query->whereNotNull($query->qualifyColumn('archived_at')),
            'all' => $query,
            default => $query->whereNull($query->qualifyColumn('archived_at')),
        };
    }

    /**
     * Tur basina kalem sayilari (katalog sekmeleri).
     *
     * @return array<string, int>
     */
    public function kindCounts(): array
    {
        return CatalogItem::query()->notArchived()
            ->selectRaw('kind, COUNT(*) AS c')
            ->groupBy('kind')
            ->pluck('c', 'kind')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Kullanim yeri basina kalem sayilari (katalogdaki Sirket ici / Saha sekmeleri, D-190).
     *
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        if (! SchemaReadiness::hasBatch('B52A')) {
            return [];
        }

        return CatalogItem::query()->notArchived()
            ->whereNotNull('usage_area')
            ->selectRaw('usage_area, COUNT(*) AS c')
            ->groupBy('usage_area')
            ->pluck('c', 'usage_area')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }
}
