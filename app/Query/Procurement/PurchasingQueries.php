<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Party\PartyRoleCode;
use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\UsageArea;
use App\Models\Party\Party;
use App\Models\Procurement\CompanyPurchase;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Query\Party\PartyQueries;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satin alma ekranlarinin ek okumalari (B52, D-189): katalog secimi (sirket
 * ici kalemler once), tedarikci secimi (Tedarikci tipindeki taraflar once),
 * dovizin son kullanilan TL kuru, Sirket ici alimlar sekme sayilari ve
 * yonetim panosunun onerilen teklifleri. Ekran siniflari sorgu yazmaz.
 */
final class PurchasingQueries
{
    public function __construct(
        private readonly CatalogQueries $catalog,
        private readonly PurchaseNeedQueries $needs,
        private readonly CompanyPurchaseQueries $companyPurchases,
        private readonly PartyQueries $parties,
    ) {}

    /**
     * Siparis edilebilen katalog kalemleri; secilen kullanim yerinin kalemleri
     * (varsayilan Sirket ici: bilgisayar, yazici, yazilim, kirtasiye) listenin
     * basinda (D-190: kullanim yeri tur degildir).
     *
     * @return array<int, string>
     */
    public function purchasableItemOptions(string $search = '', ?UsageArea $first = UsageArea::Office): array
    {
        $kinds = array_values(array_filter(CatalogItemKind::cases(), static fn (CatalogItemKind $kind): bool => $kind->isPurchasable()));
        $preferred = $first !== null ? $this->catalog->itemOptions($search, $kinds, null, 50, $first) : [];

        return $preferred + $this->catalog->itemOptions($search, $kinds);
    }

    public function itemLabel(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        return $this->catalog->itemOptions('', null, (int) $id)[(int) $id] ?? null;
    }

    /**
     * Iki satirli secim (D-192): purchasableItemOptions ile ayni siralama ve
     * arama; deger TwoLineOption HTML'i (ad / silik kategori yolu). Select'te
     * `->allowHtml()`, `->getSearchResultsUsing(fn (string $search) =>
     * ...->purchasableItemOptionsHtml($search, $usage))` ve
     * `->getOptionLabelUsing(fn (mixed $value) => ...->itemLabelHtml($value))`
     * ile kullanilir.
     *
     * @return array<int, string>
     */
    public function purchasableItemOptionsHtml(string $search = '', ?UsageArea $first = UsageArea::Office): array
    {
        $kinds = array_values(array_filter(CatalogItemKind::cases(), static fn (CatalogItemKind $kind): bool => $kind->isPurchasable()));
        $preferred = $first !== null ? $this->catalog->itemOptionsHtml($search, $kinds, null, 50, $first) : [];

        return $preferred + $this->catalog->itemOptionsHtml($search, $kinds);
    }

    /** Secili katalog kaleminin iki satirli etiketi (arsivli kalem de). */
    public function itemLabelHtml(mixed $id): ?string
    {
        return $this->catalog->itemLabelHtml($id);
    }

    /**
     * Tedarikci secimi: Tedarikci tipindeki (acik) taraflar once, sonra
     * digerleri; arsivliler haric.
     *
     * @return array<int, string>
     */
    public function supplierOptions(string $search = '', int $limit = 50): array
    {
        $query = Party::query()
            ->whereNull('archived_at')
            ->withExists(['roles as is_supplier' => fn (Builder $roles): Builder => $roles
                ->where('role_code', PartyRoleCode::Supplier->value)
                ->whereNull('valid_until')]);

        if (trim($search) !== '') {
            $this->parties->searchByName($query, $search);
        }

        return $query
            ->orderByDesc('is_supplier')
            ->orderBy('display_name')
            ->limit($limit)
            ->get(['id', 'display_name'])
            ->mapWithKeys(static fn (Party $party): array => [(int) $party->getKey() => (string) $party->getAttribute('display_name')])
            ->all();
    }

    public function supplierLabel(mixed $id): ?string
    {
        return blank($id) ? null : $this->parties->displayName((int) $id);
    }

    /** Dovizin son girilen TL kuru (teklif formunu doldurmak icin). */
    public function lastTryRate(?string $currency): ?float
    {
        $code = strtoupper(trim((string) $currency));

        if ($code === '' || $code === 'TRY') {
            return null;
        }

        $rate = PurchaseQuote::query()
            ->where('currency_code', $code)
            ->whereNotNull('try_rate')
            ->latest('id')
            ->value('try_rate');

        return $rate !== null ? (float) $rate : null;
    }

    /** Liste suzgeci: yalniz acil kalemler. */
    public function urgentScope(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_urgent'), true);
    }

    /** Siparis kalemi listesi ve sayfasi icin bagli kayitlar. */
    public function withListRelations(Builder $query): Builder
    {
        return $query->with([
            'catalogItem.category.parent',
            'proposal.businessCase.codes',
            'project',
            'recommendedQuote.supplier',
            // D-192 (B53): Talep / Teklif / Siparis sutunlari.
            ...(SchemaReadiness::hasBatch('B53') ? ['purchaseRequest', 'quotationLines.quotation.supplier', 'orderLines.order'] : []),
        ]);
    }

    /**
     * Yonetim panosu: onerilen teklifler (en cok $limit satir), pencere icin
     * butun teklifleriyle.
     */
    public function dashboardRecommendations(int $limit = 10): Builder
    {
        return $this->needs->recommendations()
            ->with(['proposal.businessCase.codes', 'quotes.supplier', 'catalogItem.category.parent'])
            ->limit($limit);
    }

    /**
     * Sirket ici alimlar sekme sayilari (arsivliler haric).
     *
     * @return array<string, int>
     */
    public function companyPurchaseTabCounts(): array
    {
        $counts = [];

        foreach ([CompanyPurchaseQueries::TAB_ALL, CompanyPurchaseQueries::TAB_UPCOMING, CompanyPurchaseQueries::TAB_OVERDUE, CompanyPurchaseQueries::TAB_ONE_TIME] as $tab) {
            $counts[$tab] = $this->companyPurchases->tabScope(CompanyPurchase::query()->notArchived(), $tab)->count();
        }

        return $counts;
    }

    /** Sirket ici alim listesi icin bagli kayitlar. */
    public function withCompanyPurchaseRelations(Builder $query): Builder
    {
        return $query->with(['catalogItem', 'orgUnit', 'personnel', 'supplier', 'need']);
    }

    /** Kalemin teklifleri, kartlar icin tedarikci ve kalemle. */
    public function quotesOf(PurchaseNeed $need): iterable
    {
        $quotes = $need->quotes()->with('supplier')->get();

        foreach ($quotes as $quote) {
            $quote->setRelation('need', $need);
        }

        return $quotes;
    }
}
