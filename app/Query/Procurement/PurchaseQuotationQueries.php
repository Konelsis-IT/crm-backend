<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Enums\Procurement\UsageArea;
use App\Models\Party\Address;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\Party;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Models\Project\Project;
use App\Services\Platform\SchemaReadiness;
use App\Support\Money;
use App\Support\Procurement\CatalogNames;
use App\Support\Procurement\UnitResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Satin alma teklifleri ekranlarinin okumalari (D-192, B53): liste sekmeleri ve
 * arsiv suzgeci, kalem secimleri (Satin alinacaklar'daki acik kalemler, iki
 * satirli katalog kalemleri), "Toplu kalem ekle" satirlari, karsilastirma
 * matrisi ("Karsilastir" toplu eylemi) ve teklif sayfasindaki ayni kalemin
 * diger teklifleri, yonetim panosunun secilmis teklifleri ve PDF'teki
 * tedarikci iletisim bilgileri. Ekran siniflari sorgu yazmaz.
 */
final class PurchaseQuotationQueries
{
    public const TAB_ALL = 'all';

    public const TAB_DRAFT = 'draft';

    public const TAB_RECEIVED = 'received';

    public const TAB_SELECTED = 'selected';

    public const TAB_ORDERED = 'ordered';

    public const TAB_CLOSED = 'closed';

    /** @var list<string> */
    public const TABS = [self::TAB_ALL, self::TAB_DRAFT, self::TAB_RECEIVED, self::TAB_SELECTED, self::TAB_ORDERED, self::TAB_CLOSED];

    /** Toplu kalem eklemede ve kalem seciminde en cok bu kadar acik kalem. */
    public const NEED_LIMIT = 200;

    /** @var array<string, array<int, array{0: string, 1: string|null}>> Istek ici secim satirlari (her kalem satiri ayni listeyi okur). */
    private static array $rowCache = [];

    public function __construct(
        private readonly CatalogQueries $catalog,
        private readonly PurchasingQueries $purchasing,
    ) {}

    /** Liste: tedarikci, siparis ve sayilan (iptal disi) kalem sayisi. */
    public function withListRelations(Builder $query): Builder
    {
        return $query
            ->with(['supplier', 'order'])
            ->withCount(['lines as line_count' => fn (Builder $lines): Builder => $lines->where('is_cancelled', false)]);
    }

    /** Teklif sayfasi: kalemler, belgeler, tedarikci, siparis. */
    public function loadDetail(PurchaseQuotation $quotation): PurchaseQuotation
    {
        $quotation->loadMissing([
            'supplier',
            'order',
            'selectedBy',
            'createdBy',
            'lines.need.project',
            'lines.need.purchaseRequest',
            'lines.catalogItem.category.parent',
            'lines.orderLine',
        ]);

        // Kalem satirlari para birimini teklifin kendisinden okur (satir basina sorgu yok).
        foreach ($quotation->lines as $line) {
            $line->setRelation('quotation', $quotation);
        }

        return $quotation;
    }

    public function tabScope(Builder $query, string $tab): Builder
    {
        $status = $query->qualifyColumn('status');

        return match ($tab) {
            self::TAB_DRAFT => $query->where($status, PurchaseQuotationStatus::Draft->value),
            self::TAB_RECEIVED => $query->where($status, PurchaseQuotationStatus::Received->value),
            self::TAB_SELECTED => $query->where($status, PurchaseQuotationStatus::Selected->value),
            self::TAB_ORDERED => $query->where($status, PurchaseQuotationStatus::Ordered->value),
            self::TAB_CLOSED => $query->whereIn($status, [PurchaseQuotationStatus::Rejected->value, PurchaseQuotationStatus::Cancelled->value]),
            default => $query,
        };
    }

    /**
     * Sekme sayilari (arsivliler haric).
     *
     * @return array<string, int>
     */
    public function tabCounts(): array
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return [];
        }

        $counts = [];

        foreach (self::TABS as $tab) {
            $counts[$tab] = $this->tabScope(PurchaseQuotation::query()->notArchived(), $tab)->count();
        }

        return $counts;
    }

    public function archiveScope(Builder $query, string $mode): Builder
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return $query;
        }

        $column = $query->qualifyColumn('archived_at');

        return match ($mode) {
            'archived' => $query->whereNotNull($column),
            'all' => $query,
            default => $query->whereNull($column),
        };
    }

    /**
     * Teklife girebilen Satin alinacaklar kalemleri (iki satirli secim): acik /
     * teklif toplaniyor / teklif onerildi, arsivde degil, siparise girmemis.
     * Alt satir: miktar ve birim, talep kodu ya da proje, durum.
     *
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function openNeedRows(): array
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return [];
        }

        return self::$rowCache['needs'] ??= $this->needRows(
            $this->openNeedQuery()->latest('id')->limit(self::NEED_LIMIT)->get(),
        );
    }

    /**
     * Secili kalemlerin satirlari (acik listede olmayan, or. teklife zaten girmis).
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function needRowsFor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === [] || ! SchemaReadiness::hasBatch('B53')) {
            return [];
        }

        return $this->needRows(PurchaseNeed::query()->with(['project', 'purchaseRequest'])->whereKey($ids)->get());
    }

    /**
     * Satin alinacak kalem secilince kalem satirina yazilanlar (bos alanlar).
     *
     * @return array<string, mixed>
     */
    public function needPrefill(int $needId): array
    {
        $need = PurchaseNeed::query()->with('catalogItem')->find($needId);

        return $need instanceof PurchaseNeed ? $this->lineFromNeed($need) : [];
    }

    /**
     * "Toplu kalem ekle": secilen acik kalemlerin satirlari, secim sirasiyla.
     *
     * @param  array<int, int|string>  $ids
     * @return list<array<string, mixed>>
     */
    public function linesFromNeeds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $needs = $this->openNeedQuery()->with('catalogItem')->whereKey($ids)->get()->keyBy(static fn (PurchaseNeed $need): int => (int) $need->getKey());
        $lines = [];

        foreach ($ids as $id) {
            $need = $needs->get($id);

            if ($need instanceof PurchaseNeed) {
                $lines[] = $this->lineFromNeed($need);
            }
        }

        return $lines;
    }

    /**
     * Iki satirli katalog secimi: ust satir ad, alt satir kategori yolu; secilen
     * kullanim yerinin kalemleri basta. Arama ad ve esleme adlarinda
     * (CatalogQueries, Turkce harf farki gozetilmez).
     *
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function catalogRows(string $search = '', ?UsageArea $first = null): array
    {
        $key = 'catalog:'.($first?->value ?? '-').':'.CatalogNames::normalize($search);

        return self::$rowCache[$key] ??= $this->splitCatalog($first !== null
            ? $this->purchasing->purchasableItemOptions($search, $first)
            : $this->catalog->itemOptions($search, $this->purchasableKinds()));
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function catalogRowsFor(array $ids): array
    {
        $rows = [];

        foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $id) {
            $rows += $this->splitCatalog($this->catalog->itemOptions('', null, $id));
        }

        return $rows;
    }

    /** Katalog kaleminin birimi (secilince kalem satirina). */
    public function catalogUnit(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $unit = CatalogItem::query()->whereKey((int) $id)->value('unit');

        return filled($unit) ? (string) $unit : null;
    }

    /** Dovizin son girilen TL kuru: once satin alma teklifleri, sonra eski tedarikci teklifleri. */
    public function lastTryRate(?string $currency): ?float
    {
        $code = strtoupper(trim((string) $currency));

        if ($code === '' || $code === 'TRY') {
            return null;
        }

        if (SchemaReadiness::hasBatch('B53')) {
            $rate = PurchaseQuotation::query()
                ->where('currency_code', $code)
                ->whereNotNull('try_rate')
                ->latest('id')
                ->value('try_rate');

            if ($rate !== null && (float) $rate > 0) {
                return (float) $rate;
            }
        }

        return $this->purchasing->lastTryRate($code);
    }

    /** Teklif kalemlerinin tek projesi (siparise donusturme penceresinin varsayilani). */
    public function singleProjectId(PurchaseQuotation $quotation): ?int
    {
        $ids = $quotation->lines()
            ->whereNotNull('purchase_need_id')
            ->with('need:id,project_id')
            ->get()
            ->map(static fn (PurchaseQuotationLine $line): ?int => $line->need?->getAttribute('project_id') !== null ? (int) $line->need->getAttribute('project_id') : null)
            ->filter()
            ->unique()
            ->values();

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * Teklif kalemlerinin projeleri ("PRJ-2026-0003 · Ad"), tekrarsiz.
     *
     * @return list<string>
     */
    public function projectLabels(PurchaseQuotation $quotation): array
    {
        $labels = [];

        foreach ($quotation->lines as $line) {
            /** @var PurchaseQuotationLine $line */
            $project = $line->need?->project;

            if ($project instanceof Project) {
                $labels[(int) $project->getKey()] = (string) $project->getAttribute('display_name');
            }
        }

        return array_values($labels);
    }

    /**
     * "Karsilastir" (toplu eylem): satirlar kalemler (Satin alinacak kalem, yoksa
     * katalog kalemi, yoksa aciklama), sutunlar teklifler. Hucrede birim fiyat
     * ve satir tutari; satirin en ucuz hucresi (TL karsiligiyla, opsiyon haric,
     * en az iki fiyatli hucrede) isaretlenir. Iptal kalemler hucre acmaz.
     *
     * @param  iterable<PurchaseQuotation>  $quotations
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, best_total: int|null}
     */
    public function comparisonMatrix(iterable $quotations): array
    {
        $ids = [];

        foreach ($quotations as $quotation) {
            if ($quotation instanceof PurchaseQuotation) {
                $ids[] = (int) $quotation->getKey();
            }
        }

        $list = PurchaseQuotation::query()
            ->with(['supplier', 'lines.need', 'lines.catalogItem'])
            ->whereKey($ids)
            ->orderBy('id')
            ->get();

        $columns = [];
        $rows = [];

        foreach ($list as $quotation) {
            /** @var PurchaseQuotation $quotation */
            $qid = (int) $quotation->getKey();
            $rate = self::rateOf($quotation);
            $columns[] = [
                'id' => $qid,
                'code' => (string) $quotation->getAttribute('quotation_no'),
                'supplier' => $quotation->supplierLabel(),
                'currency' => (string) $quotation->getAttribute('currency_code'),
                'total' => (float) $quotation->getAttribute('total_amount'),
                'total_try' => $quotation->totalInTry(),
                'status' => $quotation->statusEnum(),
                'delivery_on' => $quotation->getAttribute('delivery_on')?->format('d.m.Y'),
                'lead_time_days' => $quotation->getAttribute('lead_time_days'),
            ];

            foreach ($quotation->lines as $line) {
                /** @var PurchaseQuotationLine $line */
                if ((bool) $line->getAttribute('is_cancelled')) {
                    continue;
                }

                [$key, $label, $sub] = self::rowIdentity($line);
                $rows[$key] ??= ['label' => $label, 'sub' => $sub, 'cells' => []];
                $cell = $rows[$key]['cells'][$qid] ?? ['unit_price' => 0.0, 'total' => 0.0, 'quantity' => 0.0, 'currency' => (string) $quotation->getAttribute('currency_code'), 'option' => true, 'total_try' => null, 'best' => false];
                $cell['unit_price'] = (float) $line->getAttribute('unit_price');
                $cell['quantity'] += (float) $line->getAttribute('quantity');
                $cell['total'] += (float) $line->getAttribute('line_total');
                $cell['option'] = $cell['option'] && (bool) $line->getAttribute('is_option');
                $cell['total_try'] = $rate !== null ? $cell['total'] * $rate : null;
                $rows[$key]['cells'][$qid] = $cell;
            }
        }

        foreach ($rows as $key => $row) {
            $priced = array_filter($row['cells'], static fn (array $cell): bool => ! $cell['option'] && $cell['total_try'] !== null && $cell['total'] > 0);

            if (count($priced) < 2) {
                continue;
            }

            $best = min(array_column($priced, 'total_try'));

            foreach ($priced as $qid => $cell) {
                $rows[$key]['cells'][$qid]['best'] = abs($cell['total_try'] - $best) < 0.005;
            }
        }

        $totals = array_filter(array_column($columns, 'total_try', 'id'), static fn (?float $value): bool => $value !== null && $value > 0);

        return [
            'columns' => $columns,
            'rows' => array_values($rows),
            'best_total' => count($totals) >= 2 ? (int) array_search(min($totals), $totals, true) : null,
        ];
    }

    /**
     * Teklif sayfasindaki "Karsilastirma": bu teklifteki her Satin alinacak kalem
     * icin ayni kalemin butun tekliflerdeki satirlari (bu teklif dahil, arsivli
     * ve iptal kalemler haric), TL karsiligiyla ucuzdan pahaliya.
     *
     * @return list<array{need: PurchaseNeed, lines: list<array{line: PurchaseQuotationLine, quotation: PurchaseQuotation, unit_try: float|null, total_try: float|null, own: bool}>}>
     */
    public function relatedOffers(PurchaseQuotation $quotation): array
    {
        $needIds = $quotation->lines()
            ->whereNotNull('purchase_need_id')
            ->where('is_cancelled', false)
            ->pluck('purchase_need_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($needIds === []) {
            return [];
        }

        $lines = PurchaseQuotationLine::query()
            ->whereIn('purchase_need_id', $needIds)
            ->where('is_cancelled', false)
            ->whereHas('quotation', fn (Builder $other): Builder => $other->whereNull('archived_at'))
            ->with(['quotation.supplier', 'need.project'])
            ->get();

        $groups = [];

        foreach ($lines as $line) {
            /** @var PurchaseQuotationLine $line */
            $other = $line->quotation;
            $need = $line->need;

            if (! $other instanceof PurchaseQuotation || ! $need instanceof PurchaseNeed) {
                continue;
            }

            $rate = self::rateOf($other);
            $needId = (int) $need->getKey();
            $groups[$needId] ??= ['need' => $need, 'lines' => []];
            $groups[$needId]['lines'][] = [
                'line' => $line,
                'quotation' => $other,
                'unit_try' => $rate !== null ? (float) $line->getAttribute('unit_price') * $rate : null,
                'total_try' => $rate !== null ? (float) $line->getAttribute('line_total') * $rate : null,
                'own' => (int) $other->getKey() === (int) $quotation->getKey(),
            ];
        }

        $ordered = [];

        foreach ($needIds as $needId) {
            if (! isset($groups[$needId])) {
                continue;
            }

            usort($groups[$needId]['lines'], static fn (array $a, array $b): int => ($a['unit_try'] ?? PHP_FLOAT_MAX) <=> ($b['unit_try'] ?? PHP_FLOAT_MAX));
            $ordered[] = $groups[$needId];
        }

        return $ordered;
    }

    /**
     * Yonetim panosu (B53): secilmis ve henuz siparise donusmemis teklifler,
     * en yeni secim once.
     */
    public function selectedWithoutOrder(int $limit = 10): Builder
    {
        return PurchaseQuotation::query()
            ->notArchived()
            ->where('status', PurchaseQuotationStatus::Selected->value)
            ->whereDoesntHave('order')
            ->with(['supplier', 'lines.need.project'])
            ->orderByDesc('selected_at')
            ->orderByDesc('id')
            ->limit($limit);
    }

    /**
     * PDF'teki tedarikci iletisim bilgileri: kurumun kendi telefon / e-posta
     * kanali (birincil once) ve birincil adresi.
     *
     * @return array{phone: string|null, email: string|null, address: string|null}
     */
    public function supplierContact(?Party $party): array
    {
        if (! $party instanceof Party || ! $party->exists) {
            return ['phone' => null, 'email' => null, 'address' => null];
        }

        $points = $party->ownCommunicationPoints()->orderByDesc('is_primary')->orderBy('id')->get();
        $pick = static function (array $types) use ($points): ?string {
            $point = $points->first(static fn (CommunicationPoint $point): bool => in_array($point->getAttribute('channel_type'), $types, true));

            return $point instanceof CommunicationPoint && filled($point->getAttribute('value')) ? (string) $point->getAttribute('value') : null;
        };

        /** @var Address|null $address */
        $address = $party->addresses()->orderByDesc('is_primary')->orderBy('id')->first();
        $addressText = $address instanceof Address
            ? implode(', ', array_values(array_filter([
                $address->getAttribute('line1'),
                $address->getAttribute('line2'),
                trim(((string) $address->getAttribute('district')).' / '.((string) $address->getAttribute('city')), ' /'),
            ], static fn (mixed $part): bool => filled($part))))
            : null;

        return [
            'phone' => $pick([CommunicationChannelType::Phone, CommunicationChannelType::Mobile]),
            'email' => $pick([CommunicationChannelType::Email]),
            'address' => filled($addressText) ? $addressText : null,
        ];
    }

    private function openNeedQuery(): Builder
    {
        return PurchaseNeed::query()
            ->notArchived()
            ->with(['project', 'purchaseRequest'])
            ->whereIn('status', [PurchaseNeedStatus::Open->value, PurchaseNeedStatus::Quoting->value, PurchaseNeedStatus::Recommended->value])
            ->whereDoesntHave('orderLines');
    }

    /**
     * @param  iterable<PurchaseNeed>  $needs
     * @return array<int, array{0: string, 1: string|null}>
     */
    private function needRows(iterable $needs): array
    {
        $rows = [];

        foreach ($needs as $need) {
            /** @var PurchaseNeed $need */
            $parts = [
                trim((Money::number($need->getAttribute('quantity'), 2) ?? '').' '.(string) app(UnitResolver::class)->display($need)),
                $need->purchaseRequest?->getAttribute('request_no'),
                $need->project?->getAttribute('display_name'),
                $need->statusEnum()->getLabel(),
            ];
            $rows[(int) $need->getKey()] = [
                Str::limit((string) $need->getAttribute('title'), 120),
                implode(' · ', array_values(array_filter($parts, static fn (mixed $part): bool => filled($part)))),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function lineFromNeed(PurchaseNeed $need): array
    {
        return [
            'purchase_need_id' => (int) $need->getKey(),
            'catalog_item_id' => $need->getAttribute('catalog_item_id') !== null ? (int) $need->getAttribute('catalog_item_id') : null,
            'description' => Str::limit((string) $need->getAttribute('title'), 495, ''),
            'brand_model' => $need->getAttribute('brand_model'),
            'quantity' => (float) $need->getAttribute('quantity'),
            'unit' => $need->getAttribute('unit') ?? $need->catalogItem?->getAttribute('unit'),
            // D-194 (B54): kalemin birimi sabittir; yoksa satin alinacak kalemin birimi.
            ...(UnitResolver::enabled() ? [
                'uom_id' => app(UnitResolver::class)->itemUnitId($need->getAttribute('catalog_item_id')) ?? $need->getAttribute('uom_id'),
            ] : []),
        ];
    }

    /**
     * "Ad · Kategori › Alt kategori" etiketlerini iki satira boler.
     *
     * @param  array<int, string>  $options
     * @return array<int, array{0: string, 1: string|null}>
     */
    private function splitCatalog(array $options): array
    {
        $rows = [];

        foreach ($options as $id => $label) {
            $position = mb_strrpos($label, ' · ');
            $rows[(int) $id] = $position === false
                ? [$label, null]
                : [mb_substr($label, 0, $position), mb_substr($label, $position + 3)];
        }

        return $rows;
    }

    /**
     * @return list<CatalogItemKind>
     */
    private function purchasableKinds(): array
    {
        return array_values(array_filter(CatalogItemKind::cases(), static fn (CatalogItemKind $kind): bool => $kind->isPurchasable()));
    }

    /**
     * Matris satirinin kimligi: Satin alinacak kalem, katalog kalemi ya da aciklama.
     *
     * @return array{0: string, 1: string, 2: string|null}
     */
    private static function rowIdentity(PurchaseQuotationLine $line): array
    {
        $need = $line->need;
        $unit = trim((Money::number($line->getAttribute('quantity'), 2) ?? '').' '.(string) app(UnitResolver::class)->display($line));

        if ($need instanceof PurchaseNeed) {
            return ['need:'.$need->getKey(), (string) $need->getAttribute('title'), $unit];
        }

        $item = $line->catalogItem;

        if ($item !== null) {
            return ['item:'.$item->getKey(), (string) $item->getAttribute('name'), $unit];
        }

        $text = (string) $line->getAttribute('description');

        return ['text:'.CatalogNames::normalize($text), $text, $unit];
    }

    /** Teklifin TL kuru (TL teklifte 1; kur yoksa null). */
    private static function rateOf(PurchaseQuotation $quotation): ?float
    {
        if (strtoupper((string) $quotation->getAttribute('currency_code')) === 'TRY') {
            return 1.0;
        }

        $rate = $quotation->getAttribute('try_rate');

        return $rate !== null && (float) $rate > 0 ? (float) $rate : null;
    }
}
