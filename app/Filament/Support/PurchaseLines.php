<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Procurement\PurchaseQuotationLine;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Kalemli satin alma formlarinin ortak kalem listesi (D-192; kullanicinin
 * ekran goruntuleri 3, 4 ve 5): "Kalem ekle" (kesik cizgili, tam genislikte
 * dugme), "Tümünü küçült / Tümünü genişlet", surukleyerek siralama ve kalem
 * basliginda rozetler (sira no, miktar, tutar, listede / Konelsis AI acti /
 * listede yok, opsiyon, iptal). Kayitli kalemler kucultulmus, yeni kalem acik
 * gelir. Rozet HTML'i kullanici onayiyla (D-192: "kalem rozetleri bu sekilde
 * yapilir"); metinler her zaman kacirilir (e()). Gorunum
 * resources/css/filament/konelsis-purchasing.css `.kcp-*` kurallaridir.
 *
 * Toplamlar ekranda canli hesaplanir (totals()); kayitta servis ayni kuralla
 * yeniden hesaplar (PurchaseQuotationService::refreshTotals).
 */
final class PurchaseLines
{
    public const TONE_GRAY = 'gray';

    public const TONE_GREEN = 'green';

    public const TONE_AMBER = 'amber';

    public const TONE_RED = 'red';

    public const TONE_BLUE = 'blue';

    public const TONE_PURPLE = 'purple';

    /** Kalem listesinin ortak davranisi ve gorunumu. */
    public static function configure(Repeater $repeater): Repeater
    {
        return $repeater
            ->hiddenLabel()
            ->extraAttributes(['class' => 'kcp-lines'])
            ->collapsible()
            // Kayitli kalem kucuk (basliktaki rozetler ozetler), yeni kalem acik gelir.
            ->collapsed(static fn (?Schema $item): bool => filled(data_get($item?->getRawState(), 'id')))
            ->reorderable()
            ->reorderableWithDragAndDrop()
            ->addActionLabel(__('procurement.lines.add'))
            ->addActionAlignment(Alignment::Center)
            ->addAction(static fn (Action $action): Action => $action
                ->icon(Heroicon::Plus)
                ->extraAttributes(['class' => 'kcp-add-line'], merge: true))
            ->collapseAllAction(static fn (Action $action): Action => $action
                ->label(__('procurement.lines.collapse_all'))
                ->icon(Heroicon::ChevronUp))
            ->expandAllAction(static fn (Action $action): Action => $action
                ->label(__('procurement.lines.expand_all'))
                ->icon(Heroicon::ChevronDown)
                ->color(ActionColors::NEUTRAL))
            ->deleteAction(static fn (Action $action): Action => $action->tooltip(__('procurement.lines.delete')));
    }

    /**
     * Satin alma teklifi kaleminin basligi: aciklama (yoksa "Yeni kalem") ve
     * rozetler.
     *
     * @param  array<string, mixed>  $state
     */
    public static function quotationHeader(array $state, ?int $index, mixed $currency, ?string $catalogName = null): HtmlString
    {
        $quantity = Money::parse($state['quantity'] ?? null) ?? 0.0;
        $unitPrice = Money::parse($state['unit_price'] ?? null) ?? 0.0;
        $discount = Money::parse($state['discount_rate'] ?? null) ?? 0.0;
        $total = PurchaseQuotationLine::computeTotal($quantity, $unitPrice, $discount);
        $title = trim((string) ($state['description'] ?? ''));
        $title = $title !== '' ? $title : ($catalogName ?? __('procurement.lines.new_item'));

        $badges = [
            // D-194 (B54): secili sabit birimin adi; oncesinde serbest metin.
            [self::quantityText($quantity, UnitSelect::stateText($state)), self::TONE_GRAY],
            [__('procurement.lines.badges.amount', ['amount' => Money::format($total, $currency, Money::format(0, $currency))]), self::TONE_GRAY],
        ];

        if ($discount > 0) {
            $badges[] = [__('procurement.lines.badges.discount', ['rate' => Money::number($discount, 2)]), self::TONE_BLUE];
        }

        $badges[] = match (true) {
            (bool) ($state['ai_created_item'] ?? false) => [__('procurement.lines.badges.ai_created'), self::TONE_PURPLE],
            filled($state['catalog_item_id'] ?? null) => [__('procurement.lines.badges.in_catalog'), self::TONE_GREEN],
            default => [__('procurement.lines.badges.not_in_catalog'), self::TONE_AMBER],
        };

        if ((bool) ($state['is_option'] ?? false)) {
            $badges[] = [__('procurement.lines.badges.option'), self::TONE_BLUE];
        }

        if ((bool) ($state['is_cancelled'] ?? false)) {
            $badges[] = [__('procurement.lines.badges.cancelled'), self::TONE_RED];
        }

        return self::header($index, $title, $badges, (bool) ($state['is_cancelled'] ?? false));
    }

    /**
     * Sirket ici alim kaleminin basligi: kalem adi, miktar, tutar, yenileme.
     *
     * @param  array<string, mixed>  $state
     */
    public static function companyPurchaseHeader(array $state, ?int $index, mixed $currency, ?string $catalogName = null): HtmlString
    {
        $quantity = Money::parse($state['quantity'] ?? null) ?? 0.0;
        $unitPrice = Money::parse($state['unit_price'] ?? null) ?? 0.0;
        $title = trim((string) ($state['description'] ?? ''));
        $title = $catalogName ?? ($title !== '' ? $title : __('procurement.lines.new_item'));

        $badges = [
            [self::quantityText($quantity, null), self::TONE_GRAY],
            [__('procurement.lines.badges.amount', ['amount' => Money::format($quantity * $unitPrice, $currency, Money::format(0, $currency))]), self::TONE_GRAY],
        ];

        $badges[] = filled($state['catalog_item_id'] ?? null)
            ? [__('procurement.lines.badges.in_catalog'), self::TONE_GREEN]
            : [__('procurement.lines.badges.not_in_catalog'), self::TONE_AMBER];

        if (filled($state['renewal_months'] ?? null) && (int) $state['renewal_months'] > 0) {
            $badges[] = [__('procurement.lines.badges.renewal', ['months' => (int) $state['renewal_months']]), self::TONE_BLUE];
        }

        if (filled($state['purchase_need_id'] ?? null)) {
            $badges[] = [__('procurement.lines.badges.linked_need'), self::TONE_GREEN];
        }

        return self::header($index, $title, $badges);
    }

    /**
     * Teklif toplamlari (servisle ayni kural): opsiyon ve iptal kalemler
     * sayilmaz; ara toplam = miktar x birim fiyat, kalem indirimi = ara toplam
     * - kalem tutarlari, toplam indirim = kalem indirimi + genel indirim.
     *
     * @param  array<int|string, mixed>  $lines
     * @return array{subtotal: float, line_discount: float, general_discount: float, discount_total: float, total: float}
     */
    public static function totals(array $lines, mixed $generalDiscount): array
    {
        $subtotal = 0.0;
        $net = 0.0;

        foreach ($lines as $line) {
            if (! is_array($line) || (bool) ($line['is_option'] ?? false) || (bool) ($line['is_cancelled'] ?? false)) {
                continue;
            }

            $quantity = Money::parse($line['quantity'] ?? null) ?? 0.0;
            $unitPrice = Money::parse($line['unit_price'] ?? null) ?? 0.0;
            $subtotal += round($quantity * $unitPrice, 4);
            $net += PurchaseQuotationLine::computeTotal($quantity, $unitPrice, Money::parse($line['discount_rate'] ?? null) ?? 0.0);
        }

        $general = max(0.0, Money::parse($generalDiscount) ?? 0.0);
        $lineDiscount = round($subtotal - $net, 4);
        $discount = round($lineDiscount + $general, 4);

        return [
            'subtotal' => round($subtotal, 4),
            'line_discount' => $lineDiscount,
            'general_discount' => $general,
            'discount_total' => $discount,
            'total' => max(0.0, round($subtotal - $discount, 4)),
        ];
    }

    /** Tek rozet (tablo hucresi ve kalem basligi). */
    public static function badge(string $text, string $tone = self::TONE_GRAY): string
    {
        return '<span class="kcp-badge kcp-badge-'.e($tone).'">'.e($text).'</span>';
    }

    /**
     * Rozet listesi (tablo hucresi icin).
     *
     * @param  list<array{0: string, 1: string}>  $badges
     */
    public static function badges(array $badges): HtmlString
    {
        return new HtmlString('<span class="kcp-badges">'.implode('', array_map(static fn (array $badge): string => self::badge($badge[0], $badge[1]), $badges)).'</span>');
    }

    /**
     * Kaydedilmis teklif kaleminin rozetleri (ayrinti sayfasi tablosu).
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function lineBadges(PurchaseQuotationLine $line): array
    {
        $badges = [match (true) {
            (bool) $line->getAttribute('ai_created_item') => [__('procurement.lines.badges.ai_created'), self::TONE_PURPLE],
            $line->getAttribute('catalog_item_id') !== null => [__('procurement.lines.badges.in_catalog'), self::TONE_GREEN],
            default => [__('procurement.lines.badges.not_in_catalog'), self::TONE_AMBER],
        }];

        if ((bool) $line->getAttribute('is_option')) {
            $badges[] = [__('procurement.lines.badges.option'), self::TONE_BLUE];
        }

        if ((bool) $line->getAttribute('is_cancelled')) {
            $badges[] = [__('procurement.lines.badges.cancelled'), self::TONE_RED];
        }

        return $badges;
    }

    public static function quantityText(mixed $quantity, mixed $unit): string
    {
        $unit = trim((string) $unit);

        return trim((Money::number($quantity, 4) ?? '0').' '.($unit !== '' ? $unit : __('procurement.lines.default_unit')));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $badges
     */
    private static function header(?int $index, string $title, array $badges, bool $struck = false): HtmlString
    {
        $number = $index !== null ? '<span class="kcp-line-no">'.e((string) ($index + 1)).'</span>' : '';
        $titleClass = $struck ? 'kcp-line-title kcp-line-title-struck' : 'kcp-line-title';

        return new HtmlString(
            '<span class="kcp-line-head">'
            .'<span class="kcp-line-top">'.$number.'<span class="'.$titleClass.'">'.e($title).'</span></span>'
            .'<span class="kcp-badges">'.implode('', array_map(static fn (array $badge): string => self::badge($badge[0], $badge[1]), $badges)).'</span>'
            .'</span>'
        );
    }
}
