<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\UsageArea;
use App\Models\Procurement\CatalogCategory;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Models\Procurement\PurchaseQuote;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CatalogCategoryService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Katalog ekranlarinin okumalari (B52, D-189): Katalog listesi (kategori yolu,
 * teklif satiri ve siparis kalemi sayilari), Katalog kategorileri listesi
 * (ust kategori, kalem sayisi, tur sekmeleri) ve kalem sayfasinin
 * "Siparisler" sekmesi (butun siparis kalemlerinin tedarikci teklifleri).
 */
final class CatalogScreenQueries
{
    /** Katalog listesi: kategori yolu ve sayilar. */
    public function itemList(Builder $query): Builder
    {
        return $query
            ->with(['category.parent'])
            ->withCount(['costLines', 'needs']);
    }

    public function itemsOfKind(Builder $query, CatalogItemKind $kind): Builder
    {
        return $query->where($query->qualifyColumn('kind'), $kind->value);
    }

    /** Kullanim yerine gore (D-190): tur ne olursa olsun Sirket ici / Saha kalemleri. */
    public function itemsOfUsage(Builder $query, UsageArea $usage): Builder
    {
        return $query->where($query->qualifyColumn('usage_area'), $usage->value);
    }

    /** Kategori listesi: ust kategori ve kalem sayisi. */
    public function categoryList(Builder $query): Builder
    {
        return $query
            ->with(['parent'])
            ->withCount(['items']);
    }

    public function categoriesOfKind(Builder $query, CatalogItemKind $kind): Builder
    {
        return $query->where($query->qualifyColumn('kind'), CatalogCategoryService::treeKind($kind)->value);
    }

    /**
     * Kategori sekmelerinin rozetleri (arsivde olmayanlar; Urun ve Hizmet tek agac).
     *
     * @return array<string, int>
     */
    public function categoryKindCounts(): array
    {
        $counts = [];

        if (! SchemaReadiness::hasBatch('B52')) {
            return $counts;
        }

        foreach (CatalogCategory::query()->notArchived()->pluck('kind') as $kind) {
            $key = $kind instanceof BackedEnum ? (string) $kind->value : (string) $kind;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Ust kategori secenekleri: ayni turun agaci, duzenlenen kategori ve onun
     * alt kategorileri haric ("Insaat › Saha Duzeltme").
     *
     * @return array<int, string>
     */
    public function parentOptions(?CatalogItemKind $kind, ?int $exceptId = null): array
    {
        $options = app(CatalogQueries::class)->categoryOptions($kind);

        if ($exceptId === null) {
            return $options;
        }

        $excluded = [$exceptId];

        foreach (CatalogCategory::query()->where('parent_id', $exceptId)->pluck('id') as $childId) {
            $excluded[] = (int) $childId;
        }

        return array_diff_key($options, array_flip($excluded));
    }

    /**
     * Kalemin butun siparis kalemleri ve onlarin tedarikci teklifleri (kalem
     * sayfasindaki Siparisler sekmesi; kartlar birim fiyatla karsilastirilir).
     *
     * @return array{needs: int, quotes: list<PurchaseQuote>, best: PurchaseQuote|null}
     */
    public function itemQuotes(CatalogItem $item): array
    {
        $result = ['needs' => 0, 'quotes' => [], 'best' => null];

        if (! SchemaReadiness::hasBatch('B52')) {
            return $result;
        }

        $needs = PurchaseNeed::query()
            ->where('catalog_item_id', $item->getKey())
            ->with(['quotes.supplier', 'proposal'])
            ->latest('id')
            ->get();

        $bestValue = null;

        foreach ($needs as $need) {
            /** @var PurchaseNeed $need */
            $result['needs']++;

            foreach ($need->quotes as $quote) {
                /** @var PurchaseQuote $quote */
                $quote->setRelation('need', $need);
                $result['quotes'][] = $quote;
                $value = self::unitInTry($quote);

                if ($value !== null && ($bestValue === null || $value < $bestValue)) {
                    [$bestValue, $result['best']] = [$value, $quote];
                }
            }
        }

        // Kur yoksa (dovizli teklif, TL karsiligi girilmemis) en dusuk birim fiyat.
        if ($result['best'] === null && $result['quotes'] !== []) {
            $result['best'] = collect($result['quotes'])->sortBy(fn (PurchaseQuote $quote): float => (float) $quote->getAttribute('unit_price'))->first();
        }

        return $result;
    }

    /**
     * Kalem sayfasinin "Fiyat gecmisi" (D-194, B53; kullanici: "fiyat gecmisi
     * olacak"): bu kalemin fiyati girilmis satin alma teklifi satirlari, arsivdeki
     * teklif haric, en yeni teklif tarihi basta. Iliski doner (RelationManager).
     */
    public function priceHistory(CatalogItem $item): HasMany
    {
        return $item->quotationLines()
            ->with(['quotation.supplier', 'orderLine.order'])
            ->where('unit_price', '>', 0)
            ->whereHas('quotation', fn (Builder $quotation): Builder => $quotation->notArchived())
            ->orderByDesc(
                PurchaseQuotation::query()
                    ->select('quote_date')
                    ->whereColumn('purchase_quotations.id', 'purchase_quotation_lines.purchase_quotation_id')
                    ->limit(1),
            )
            ->orderByDesc('purchase_quotation_lines.id');
    }

    /**
     * Fiyat gecmisinin ozeti: TL karsiligi (indirimli birim fiyat x kur) en
     * dusuk, en son ve ortalama; iptal satiri ve kuru olmayan dovizli teklif girmez.
     *
     * @return array{lowest: float|null, last: float|null, average: float|null, count: int, missing_rate: int}
     */
    public function priceSummary(CatalogItem $item): array
    {
        $values = [];
        $missingRate = 0;

        foreach ($this->priceHistory($item)->get() as $line) {
            /** @var PurchaseQuotationLine $line */
            if ((bool) $line->getAttribute('is_cancelled')) {
                continue;
            }

            $value = self::lineUnitInTry($line);

            if ($value === null) {
                $missingRate++;

                continue;
            }

            $values[] = $value;
        }

        return [
            'lowest' => $values === [] ? null : min($values),
            // Liste en yeni teklifle basladigi icin ilk deger en son fiyattir.
            'last' => $values[0] ?? null,
            'average' => $values === [] ? null : array_sum($values) / count($values),
            'count' => count($values),
            'missing_rate' => $missingRate,
        ];
    }

    /** Satirin indirimli birim fiyatinin TL karsiligi (TL teklifte kendisi; kur yoksa null). */
    public static function lineUnitInTry(PurchaseQuotationLine $line): ?float
    {
        $quantity = (float) $line->getAttribute('quantity');
        $net = $quantity > 0 ? (float) $line->getAttribute('line_total') / $quantity : (float) $line->getAttribute('unit_price');
        $quotation = $line->quotation;

        if (! $quotation instanceof PurchaseQuotation) {
            return null;
        }

        if (strtoupper((string) $quotation->getAttribute('currency_code')) === 'TRY') {
            return $net;
        }

        $rate = $quotation->getAttribute('try_rate');

        return $rate !== null && (float) $rate > 0 ? $net * (float) $rate : null;
    }

    /**
     * Fiyat gecmisindeki tedarikciler (suzgec): taraf kaydi `p:ID`, serbest ad `n:ad`.
     *
     * @return array<string, string>
     */
    public function priceHistorySuppliers(CatalogItem $item): array
    {
        $options = [];

        foreach ($this->priceHistory($item)->get() as $line) {
            /** @var PurchaseQuotationLine $line */
            $quotation = $line->quotation;

            if (! $quotation instanceof PurchaseQuotation) {
                continue;
            }

            $partyId = $quotation->getAttribute('supplier_party_id');
            $name = trim((string) $quotation->getAttribute('supplier_name'));
            $key = $partyId !== null ? 'p:'.(int) $partyId : ($name !== '' ? 'n:'.$name : null);

            if ($key !== null) {
                $options[$key] ??= $quotation->supplierLabel();
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** Tedarikci suzgeci (`p:ID` ya da `n:ad`). */
    public function priceHistorySupplierScope(Builder $query, mixed $value): Builder
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $query;
        }

        return $query->whereHas('quotation', function (Builder $quotation) use ($value): Builder {
            return str_starts_with($value, 'p:')
                ? $quotation->where('supplier_party_id', (int) substr($value, 2))
                : $quotation->whereNull('supplier_party_id')->where('supplier_name', substr($value, 2));
        });
    }

    /**
     * Teklif durumu suzgeci.
     *
     * @param  array<int, string>  $statuses
     */
    public function priceHistoryStatusScope(Builder $query, array $statuses): Builder
    {
        $statuses = array_values(array_filter(array_map('strval', $statuses)));

        return $statuses === []
            ? $query
            : $query->whereHas('quotation', fn (Builder $quotation): Builder => $quotation->whereIn('status', $statuses));
    }

    private static function unitInTry(PurchaseQuote $quote): ?float
    {
        $unit = (float) $quote->getAttribute('unit_price');

        if (strtoupper((string) $quote->getAttribute('currency_code')) === 'TRY') {
            return $unit;
        }

        $rate = $quote->getAttribute('try_rate');

        return $rate !== null && (float) $rate > 0 ? $unit * (float) $rate : null;
    }
}
