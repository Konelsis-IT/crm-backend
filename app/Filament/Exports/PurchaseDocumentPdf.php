<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Query\Procurement\PurchaseQuotationQueries;
use App\Support\DisplayTime;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satin alma teklifi ve siparis PDF'i (D-192; kullanici: "8. ekran goruntusu
 * bizdeki tekliflerin standart pdf goruntuleme sekli olur"). Gorunum ekran
 * goruntusu 8'in duzenidir: ust baslik bandi (belge adi, durum), kurum karti
 * (logo, kurum adi; sagda turuncu belge kodu ve tarihler), tedarikci bilgileri,
 * mavi baslikli kalem tablosu (sira, aciklama, miktar, birim, birim fiyat,
 * indirim, tutar), sagda toplamlar (toplam, kirmizi indirim, turuncu son
 * tutar), sartlar / teslimat ve alt bilgi. dompdf + DejaVu Sans (Turkce
 * harfler ve para simgeleri); sablonlar resources/views/pdf/purchase-*.blade.php
 * (Blade, kullanici onayi D-192).
 *
 * viewData metotlari kaydedilmemis modelle de calisir (iliskiler
 * setRelation ile verilebilir); boylece gorunum veritabanina yazmadan
 * denenebilir.
 */
final class PurchaseDocumentPdf
{
    public function __construct(private readonly PurchaseQuotationQueries $queries) {}

    public function quotation(PurchaseQuotation $quotation): StreamedResponse
    {
        Gate::authorize('view', $quotation);

        return $this->stream($this->render('pdf.purchase-quotation', $this->quotationData($quotation)), (string) $quotation->getAttribute('quotation_no'), $quotation->supplierLabel());
    }

    public function order(PurchaseOrder $order): StreamedResponse
    {
        Gate::authorize('view', $order);

        return $this->stream($this->render('pdf.purchase-order', $this->orderData($order)), (string) $order->getAttribute('order_no'), $order->supplierLabel());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data): string
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);

        return (string) $pdf->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function quotationData(PurchaseQuotation $quotation): array
    {
        $currency = $quotation->getAttribute('currency_code');
        $lines = $this->collection($quotation, 'lines');
        $general = (float) $quotation->getAttribute('general_discount');
        $discount = (float) $quotation->getAttribute('discount_total');
        $lineDiscount = max(0.0, round($discount - $general, 4));

        return [
            ...$this->common($quotation->getAttribute('created_at'), $quotation->createdBy ?? null),
            'kind' => 'quotation',
            'title' => __('procurement.pdf.quotation_title'),
            'status' => (string) $quotation->statusEnum()->getLabel(),
            'code' => (string) $quotation->getAttribute('quotation_no'),
            'dates' => array_values(array_filter([
                self::fact(__('procurement.pdf.date'), self::date($quotation->getAttribute('quote_date')) ?? self::date($quotation->getAttribute('created_at'))),
                self::fact(__('procurement.pdf.valid_until'), self::date($quotation->getAttribute('valid_until'))),
            ])),
            'supplier' => $this->supplier($quotation->supplier, $quotation->supplierLabel(), $quotation->getAttribute('supplier_reference')),
            'lines' => $lines->values()->map(fn (PurchaseQuotationLine $line, int $index): array => $this->line($line, $index, $currency, ! $line->counts(), self::flag($line)))->all(),
            'totals' => $this->totals(
                (float) $quotation->getAttribute('subtotal'),
                $lineDiscount,
                $general,
                (float) $quotation->getAttribute('total_amount'),
                $currency,
                $quotation->totalInTry(),
                $quotation->getAttribute('try_rate'),
            ),
            'facts' => array_values(array_filter([
                self::fact(__('procurement.pdf.payment_terms'), $quotation->getAttribute('payment_terms')),
                self::fact(__('procurement.pdf.delivery_terms'), $quotation->getAttribute('delivery_terms')),
                self::fact(__('procurement.pdf.lead_time'), $quotation->getAttribute('lead_time_days') !== null ? __('procurement.pdf.days', ['n' => $quotation->getAttribute('lead_time_days')]) : null),
                self::fact(__('procurement.pdf.delivery_on'), self::date($quotation->getAttribute('delivery_on'))),
            ])),
            'factsTitle' => __('procurement.pdf.terms'),
            'note' => filled($quotation->getAttribute('note')) ? (string) $quotation->getAttribute('note') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderData(PurchaseOrder $order): array
    {
        $currency = $order->getAttribute('currency_code');
        $lines = $this->collection($order, 'lines');
        $quotation = $order->relationLoaded('quotation') || $order->exists ? $order->quotation : null;
        $general = $quotation instanceof PurchaseQuotation ? (float) $quotation->getAttribute('general_discount') : 0.0;
        $discount = (float) $order->getAttribute('discount_total');
        $from = self::date($order->getAttribute('delivery_from'));
        $until = self::date($order->getAttribute('delivery_until'));
        $project = $order->relationLoaded('project') || $order->exists ? $order->project : null;

        return [
            ...$this->common($order->getAttribute('ordered_at') ?? $order->getAttribute('created_at'), $order->orderedBy ?? $order->createdBy ?? null),
            'kind' => 'order',
            'title' => __('procurement.pdf.order_title'),
            'status' => (string) $order->statusEnum()->getLabel(),
            'code' => (string) $order->getAttribute('order_no'),
            'dates' => array_values(array_filter([
                self::fact(__('procurement.pdf.date'), self::date($order->getAttribute('ordered_at')) ?? self::date($order->getAttribute('created_at'))),
                self::fact(__('procurement.pdf.expected_delivery'), self::date($order->getAttribute('expected_delivery_on'))),
            ])),
            'supplier' => $this->supplier($order->supplier, $order->supplierLabel(), $quotation?->getAttribute('supplier_reference')),
            'lines' => $lines->values()->map(fn (PurchaseOrderLine $line, int $index): array => $this->line($line, $index, $currency, false, null))->all(),
            'totals' => $this->totals(
                (float) $order->getAttribute('subtotal'),
                max(0.0, round($discount - $general, 4)),
                $general,
                (float) $order->getAttribute('total_amount'),
                $currency,
                $order->totalInTry(),
                $order->getAttribute('try_rate'),
            ),
            'delivery' => array_values(array_filter([
                self::fact(__('procurement.pdf.delivery_address'), $order->getAttribute('delivery_address')),
                self::fact(__('procurement.pdf.delivery_contact'), $order->getAttribute('delivery_contact')),
                self::fact(__('procurement.pdf.delivery_period'), $from !== null || $until !== null ? trim(($from ?? '…').' – '.($until ?? '…')) : null),
                self::fact(__('procurement.pdf.expected_delivery'), self::date($order->getAttribute('expected_delivery_on'))),
                self::fact(__('procurement.pdf.carrier'), $order->getAttribute('carrier')),
                self::fact(__('procurement.pdf.tracking_no'), $order->getAttribute('tracking_no')),
                self::fact(__('procurement.pdf.project'), $project?->getAttribute('display_name')),
                self::fact(__('procurement.pdf.quotation'), $quotation?->getAttribute('quotation_no')),
            ])),
            'facts' => array_values(array_filter([
                self::fact(__('procurement.pdf.payment_terms'), $order->getAttribute('payment_terms')),
                self::fact(__('procurement.pdf.delivery_terms'), $quotation?->getAttribute('delivery_terms')),
            ])),
            'factsTitle' => __('procurement.pdf.terms'),
            'note' => filled($order->getAttribute('note')) ? (string) $order->getAttribute('note') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function common(mixed $documentDate, mixed $preparedBy): array
    {
        $logo = public_path('images/konelsis-logo.png');

        return [
            'company' => (string) config('konelsis.organization.name_tr', 'Konelsis'),
            'logo' => is_file($logo) ? $logo : null,
            'preparedBy' => $preparedBy instanceof Personnel ? (string) $preparedBy->getAttribute('full_name') : (string) (auth()->user()?->full_name ?? ''),
            'printedAt' => now()->timezone(DisplayTime::zone())->format('d.m.Y H:i'),
            'documentDate' => self::date($documentDate),
            'upper' => static fn (string $text): string => self::upper($text),
        ];
    }

    /**
     * @return array{name: string, phone: string|null, email: string|null, address: string|null, reference: string|null}
     */
    private function supplier(?Party $party, string $label, mixed $reference): array
    {
        return [
            'name' => $label !== '' ? $label : '-',
            ...$this->queries->supplierContact($party),
            'reference' => filled($reference) ? (string) $reference : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(PurchaseQuotationLine|PurchaseOrderLine $line, int $index, mixed $currency, bool $excluded, ?string $flag): array
    {
        $item = $line->relationLoaded('catalogItem') || $line->exists ? $line->catalogItem : null;
        $description = (string) $line->getAttribute('description');
        $details = array_values(array_filter([
            $line->getAttribute('brand_model'),
            $item instanceof CatalogItem && Str::lower((string) $item->getAttribute('name')) !== Str::lower($description) ? (string) $item->getAttribute('name') : null,
        ], static fn (mixed $part): bool => filled($part)));
        $discount = (float) $line->getAttribute('discount_rate');

        return [
            'no' => $index + 1,
            'description' => $description,
            'detail' => $details === [] ? null : implode(' · ', $details),
            'quantity' => Money::number($line->getAttribute('quantity'), 4) ?? '-',
            'unit' => app(UnitResolver::class)->display($line) ?? __('procurement.lines.default_unit'),
            'unit_price' => Money::format($line->getAttribute('unit_price'), $currency, '-', 4),
            'discount' => $discount > 0 ? '%'.Money::number($discount, 2) : '-',
            'amount' => Money::format($line->getAttribute('line_total'), $currency),
            'flag' => $flag,
            'excluded' => $excluded,
        ];
    }

    /**
     * @return list<array{label: string, value: string, tone: string|null}>
     */
    private function totals(float $subtotal, float $lineDiscount, float $general, float $total, mixed $currency, ?float $inTry, mixed $rate): array
    {
        $discount = round($lineDiscount + $general, 4);
        $rows = [
            ['label' => __('procurement.pdf.subtotal'), 'value' => Money::format($subtotal, $currency, Money::format(0, $currency)), 'tone' => null],
            ['label' => __('procurement.pdf.discount_total'), 'value' => ($discount > 0 ? '-' : '').Money::format($discount, $currency, Money::format(0, $currency)), 'tone' => 'red'],
            ['label' => __('procurement.pdf.grand_total'), 'value' => Money::format($total, $currency, Money::format(0, $currency)), 'tone' => 'grand'],
        ];

        if (strtoupper((string) $currency) !== 'TRY' && $inTry !== null) {
            $rows[] = ['label' => __('procurement.pdf.in_try').' ('.Money::number($rate, 4).')', 'value' => Money::format($inTry, 'TRY'), 'tone' => 'muted'];
        }

        return $rows;
    }

    /**
     * Buyuk harfli baslik (tablo basliklari, alan etiketleri). dompdf'in CSS
     * text-transform'u Turkce i / ı farkini bilmez ("Birim" -> "BIRIM");
     * Turkcede once i -> İ, ı -> I cevrilir.
     */
    public static function upper(string $text): string
    {
        if (str_starts_with(app()->getLocale(), 'tr')) {
            $text = str_replace(['i', 'ı'], ['İ', 'I'], $text);
        }

        return mb_strtoupper($text, 'UTF-8');
    }

    /**
     * Kayitli modelde iliski; kaydedilmemis modelde yalniz setRelation ile verilen.
     *
     * @return Collection<int, mixed>
     */
    private function collection(PurchaseQuotation|PurchaseOrder $record, string $relation): Collection
    {
        if ($record->relationLoaded($relation)) {
            return collect($record->getRelation($relation));
        }

        return $record->exists ? collect($record->{$relation}) : collect();
    }

    private static function flag(PurchaseQuotationLine $line): ?string
    {
        return match (true) {
            (bool) $line->getAttribute('is_cancelled') => __('procurement.pdf.cancelled'),
            (bool) $line->getAttribute('is_option') => __('procurement.pdf.option'),
            default => null,
        };
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private static function fact(string $label, mixed $value): ?array
    {
        return filled($value) ? ['label' => $label, 'value' => (string) $value] : null;
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return ($value->format('H:i:s') === '00:00:00' ? $value : $value->copy()->timezone(DisplayTime::zone()))->format('d.m.Y');
        }

        return null;
    }

    private function stream(string $bytes, string $code, string $supplier): StreamedResponse
    {
        $fileName = Str::of(trim($code.' '.$supplier))->slug()->limit(90, '')->append('.pdf')->value();

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            $fileName,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
