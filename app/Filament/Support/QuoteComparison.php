<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Models\Procurement\PurchaseQuote;
use App\Support\DisplayTime;
use App\Support\Money;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Tedarikci teklifi karsilastirma kartlari (D-189; kullanici: "tum siparisler
 * bir kart yapisiyla ekranda gorulup hizlica karsilastirma yapilabilir.
 * Birbirine gore daha avantajli olanlar iconlarla + - deger gosterimi ile
 * yesil kirmizi renk uyarilariyla gosterilebilir").
 *
 * Her teklif bir kart (Filament Section): tedarikci, marka / model, toplam,
 * birim fiyat, teslim suresi ve tarihi, odeme sarti, gecerlilik. Toplam ve
 * birim fiyat TL karsiligiyla en uygun teklife gore karsilastirilir: en uygun
 * yesil "En uygun", digerleri kirmizi "+12.400 TL (%8)". Teslim suresi en
 * hizliya gore: "En hizli" / "+3 gun". Onerilen teklif yildizli rozetle.
 * Siparis kalemi sayfasi, katalog kalemi sayfasi (Siparisler) ve yonetim
 * panosu penceresi ayni kartlari kullanir.
 *
 * D-192 (B53): satin alma teklifi sayfasinin "Karsilastirma" bolumu
 * (quotationOffers): teklifteki her Satin alinacak kalem icin ayni kalemin
 * butun satin alma tekliflerindeki satirlari ayni kart diliyle (birim fiyat
 * TL karsiligiyla en uyguna gore yesil / kirmizi, bu teklif yildizli).
 */
final class QuoteComparison
{
    /**
     * Satin alma teklifi sayfasi (D-192): kalem basina bir bolum, icinde her
     * teklifin karti. Gruplar PurchaseQuotationQueries::relatedOffers'tan gelir.
     *
     * @param  list<array{need: PurchaseNeed, lines: list<array{line: PurchaseQuotationLine, quotation: PurchaseQuotation, unit_try: float|null, total_try: float|null, own: bool}>}>  $groups
     * @return list<Component>
     */
    public static function quotationOffers(array $groups): array
    {
        if ($groups === []) {
            return [
                TextEntry::make('offers_empty')
                    ->hiddenLabel()
                    ->state(__('purchase_quotation.comparison.empty'))
                    ->color('gray'),
            ];
        }

        $sections = [];

        foreach ($groups as $groupIndex => $group) {
            $need = $group['need'];
            $values = array_filter(array_column($group['lines'], 'unit_try'), static fn (?float $value): bool => $value !== null && $value > 0);
            $best = $values === [] ? null : min($values);
            $cards = [];

            foreach ($group['lines'] as $index => $row) {
                $cards[] = self::offerCard($row, 'o'.$groupIndex.'_'.$index, $best, count($values) >= 2);
            }

            $sections[] = Section::make((string) $need->getAttribute('title'))
                ->key('offers-need-'.$need->getKey())
                ->description(__('purchase_quotation.comparison.offers', ['count' => count($group['lines'])]))
                ->icon(Heroicon::OutlinedScale)
                ->iconColor('gray')
                ->compact()
                ->collapsible()
                ->schema([Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema($cards)]);
        }

        return $sections;
    }

    /**
     * @param  array{line: PurchaseQuotationLine, quotation: PurchaseQuotation, unit_try: float|null, total_try: float|null, own: bool}  $row
     */
    private static function offerCard(array $row, string $key, ?float $best, bool $compare): Section
    {
        $line = $row['line'];
        $quotation = $row['quotation'];
        $currency = $quotation->getAttribute('currency_code');
        $foreign = strtoupper((string) $currency) !== 'TRY';
        [$diffText, $diffColor, $diffIcon] = $compare ? self::priceDiff($row['unit_try'], $best) : [null, 'gray', Heroicon::OutlinedScale];
        $delivery = array_values(array_filter([
            $quotation->getAttribute('lead_time_days') !== null ? __('procurement.quote_cards.days', ['n' => $quotation->getAttribute('lead_time_days')]) : null,
            $quotation->getAttribute('delivery_on')?->format('d.m.Y'),
        ]));
        $url = $row['own'] ? null : RecordLinks::detailUrl($quotation);

        return Section::make($quotation->supplierLabel() !== '' ? $quotation->supplierLabel() : __('purchase_quotation.values.no_supplier'))
            ->key('offer-'.$line->getKey())
            ->description(trim($quotation->getAttribute('quotation_no').($row['own'] ? ' · '.__('purchase_quotation.comparison.own') : '')))
            ->icon($row['own'] ? Heroicon::Star : Heroicon::OutlinedBuildingStorefront)
            ->iconColor($row['own'] ? 'primary' : 'gray')
            ->compact()
            ->columns(2)
            ->schema([
                TextEntry::make($key.'_total')
                    ->label(__('purchase_quotation.comparison.line_total'))
                    ->state(Money::format($line->getAttribute('line_total'), $currency))
                    ->weight('bold'),
                TextEntry::make($key.'_diff')
                    ->label(__('procurement.quote_cards.unit_compare'))
                    ->state($diffText)
                    ->badge()
                    ->color($diffColor)
                    ->icon($diffIcon)
                    ->hidden($diffText === null),
                TextEntry::make($key.'_unit')
                    ->label(__('purchase_quotation.comparison.unit_price'))
                    ->state(Money::format($line->getAttribute('unit_price'), $currency, '-', 4).' × '.Money::number($line->getAttribute('quantity'), 2)),
                TextEntry::make($key.'_try')
                    ->label(__('purchase_quotation.comparison.in_try'))
                    ->state($row['total_try'] !== null ? Money::format($row['total_try'], 'TRY') : __('purchase_quotation.comparison.no_rate'))
                    ->hidden(! $foreign),
                TextEntry::make($key.'_delivery')
                    ->label(__('purchase_quotation.comparison.delivery'))
                    ->state($delivery === [] ? null : implode(' · ', $delivery))
                    ->placeholder('-'),
                TextEntry::make($key.'_status')
                    ->label(__('purchase_quotation.comparison.status'))
                    ->state($quotation->statusEnum())
                    ->badge(),
                TextEntry::make($key.'_link')
                    ->label(__('purchase_quotation.comparison.quotation'))
                    ->state((string) $quotation->getAttribute('quotation_no'))
                    ->url($url)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->hidden($url === null),
            ]);
    }
    /**
     * @param  iterable<PurchaseQuote>  $quotes
     * @param  (Closure(PurchaseQuote): list<\Filament\Actions\Action>)|null  $actions  Kart basligindaki dugmeler.
     * @param  bool  $byUnitPrice  Katalog kalemi sayfasinda farkli miktarlar birim fiyatla karsilastirilir.
     * @return list<Component>
     */
    public static function cards(iterable $quotes, ?Closure $actions = null, bool $byUnitPrice = false, bool $showNeed = false): array
    {
        $list = [];

        foreach ($quotes as $quote) {
            if ($quote instanceof PurchaseQuote) {
                $list[] = $quote;
            }
        }

        if ($list === []) {
            return [
                TextEntry::make('quotes_empty')
                    ->hiddenLabel()
                    ->state(__('procurement.quote_cards.empty'))
                    ->color('gray'),
            ];
        }

        $metric = static fn (PurchaseQuote $quote): ?float => $byUnitPrice ? self::unitInTry($quote) : $quote->totalInTry();
        $values = array_filter(array_map($metric, $list), static fn (?float $value): bool => $value !== null);
        $best = $values === [] ? null : min($values);
        $leads = array_filter(array_map(static fn (PurchaseQuote $quote): ?int => $quote->getAttribute('lead_time_days'), $list), static fn (?int $value): bool => $value !== null);
        $fastest = $leads === [] ? null : min($leads);
        $cards = [];

        foreach ($list as $index => $quote) {
            $cards[] = self::card($quote, $index, $metric($quote), $best, $fastest, $actions, $byUnitPrice, $showNeed);
        }

        return [Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema($cards)];
    }

    private static function card(PurchaseQuote $quote, int $index, ?float $value, ?float $best, ?int $fastest, ?Closure $actions, bool $byUnitPrice, bool $showNeed): Section
    {
        $need = $quote->need;
        $recommended = $need instanceof PurchaseNeed && (int) $need->getAttribute('recommended_quote_id') === (int) $quote->getKey();
        $currency = $quote->getAttribute('currency_code');
        $key = 'q'.$quote->getKey().'_'.$index;
        [$diffText, $diffColor, $diffIcon] = self::priceDiff($value, $best);
        [$leadText, $leadColor, $leadIcon] = self::leadDiff($quote->getAttribute('lead_time_days'), $fastest);

        $entries = [
            TextEntry::make($key.'_total')
                ->label(__('procurement.quote_cards.total'))
                ->state(Money::format($quote->getAttribute('total_amount'), $currency))
                ->weight('bold')
                ->size('lg'),
            TextEntry::make($key.'_diff')
                ->label($byUnitPrice ? __('procurement.quote_cards.unit_compare') : __('procurement.quote_cards.compare'))
                ->state($diffText)
                ->badge()
                ->color($diffColor)
                ->icon($diffIcon),
            TextEntry::make($key.'_unit')
                ->label(__('procurement.quote_cards.unit_price'))
                ->state(Money::format($quote->getAttribute('unit_price'), $currency, '-', 4).' × '.Money::number($quote->getAttribute('quantity'), 2)),
            TextEntry::make($key.'_try')
                ->label(__('procurement.quote_cards.in_try'))
                ->state(strtoupper((string) $currency) === 'TRY' ? null : Money::format($quote->totalInTry(), 'TRY'))
                ->placeholder('-')
                ->hidden(strtoupper((string) $currency) === 'TRY'),
            TextEntry::make($key.'_lead')
                ->label(__('procurement.quote_cards.lead_time'))
                ->state($quote->getAttribute('lead_time_days') !== null ? __('procurement.quote_cards.days', ['n' => $quote->getAttribute('lead_time_days')]) : null)
                ->placeholder('-'),
            TextEntry::make($key.'_lead_diff')
                ->label(__('procurement.quote_cards.lead_compare'))
                ->state($leadText)
                ->badge()
                ->color($leadColor)
                ->icon($leadIcon)
                ->hidden($leadText === null),
            TextEntry::make($key.'_delivery')
                ->label(__('procurement.quote_cards.delivery_on'))
                ->state($quote->getAttribute('delivery_on')?->format('d.m.Y'))
                ->placeholder('-'),
            TextEntry::make($key.'_terms')
                ->label(__('procurement.quote_cards.payment_terms'))
                ->state($quote->getAttribute('payment_terms'))
                ->placeholder('-'),
            TextEntry::make($key.'_valid')
                ->label(__('procurement.quote_cards.valid_until'))
                ->state($quote->getAttribute('valid_until')?->format('d.m.Y'))
                ->placeholder('-')
                ->color(fn (): ?string => $quote->getAttribute('valid_until') !== null && $quote->getAttribute('valid_until')->toDateString() < now(DisplayTime::zone())->toDateString() ? 'danger' : null),
        ];

        if ($showNeed && $need instanceof PurchaseNeed) {
            array_unshift($entries, TextEntry::make($key.'_need')
                ->label(__('procurement.quote_cards.need'))
                ->state(self::needLabel($need))
                ->url(fn (): ?string => RecordLinks::detailUrl($need))
                ->icon(RecordLinks::iconFor($need)));
        }

        if (filled($quote->getAttribute('note'))) {
            $entries[] = TextEntry::make($key.'_note')
                ->label(__('procurement.quote_cards.note'))
                ->state($quote->getAttribute('note'));
        }

        $section = Section::make($quote->supplierLabel())
            ->key('quote-'.$quote->getKey())
            ->description(filled($quote->getAttribute('brand_model')) ? (string) $quote->getAttribute('brand_model') : null)
            ->icon($recommended ? Heroicon::Star : Heroicon::OutlinedBuildingStorefront)
            ->iconColor($recommended ? 'warning' : 'gray')
            ->compact()
            ->columns(2)
            ->schema($entries);

        if ($recommended) {
            $section->heading($quote->supplierLabel().' · '.__('procurement.quote_cards.recommended'));
        }

        if ($actions !== null) {
            $section->headerActions($actions($quote));
        }

        return $section;
    }

    /**
     * @return array{0: string, 1: string, 2: Heroicon}
     */
    private static function priceDiff(?float $value, ?float $best): array
    {
        if ($value === null || $best === null) {
            return [__('procurement.quote_cards.no_rate'), 'gray', Heroicon::OutlinedQuestionMarkCircle];
        }

        $diff = $value - $best;

        if (abs($diff) < 0.005) {
            return [__('procurement.quote_cards.best'), 'success', Heroicon::OutlinedCheckBadge];
        }

        $percent = $best > 0 ? ($diff / $best) * 100 : 0;

        return ['+'.Money::format($diff, 'TRY').' (%'.Money::number($percent, 1).')', 'danger', Heroicon::OutlinedArrowTrendingUp];
    }

    /**
     * @return array{0: string|null, 1: string, 2: Heroicon}
     */
    private static function leadDiff(?int $lead, ?int $fastest): array
    {
        if ($lead === null || $fastest === null) {
            return [null, 'gray', Heroicon::OutlinedClock];
        }

        if ($lead === $fastest) {
            return [__('procurement.quote_cards.fastest'), 'success', Heroicon::OutlinedBolt];
        }

        return ['+'.__('procurement.quote_cards.days', ['n' => $lead - $fastest]), 'danger', Heroicon::OutlinedClock];
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

    private static function needLabel(PurchaseNeed $need): string
    {
        $parts = [(string) $need->getAttribute('title')];
        $proposalNo = $need->proposal?->getAttribute('proposal_no');

        if (filled($proposalNo)) {
            $parts[] = (string) $proposalNo;
        }

        return implode(' · ', $parts);
    }
}
