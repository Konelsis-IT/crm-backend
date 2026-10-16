<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Procurement\PurchaseQuotation;
use App\Support\Money;

/** Satin alma teklifleri listesi (PurchaseQuotationResource) sutunlari (D-192). */
class PurchaseQuotationExporter extends KonelsisExporter
{
    protected static ?string $model = PurchaseQuotation::class;

    public static function fileLabel(): string
    {
        return __('purchase_quotation.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('quotation_no', __('purchase_quotation.fields.quotation_no')),
            self::text('supplier_label', __('purchase_quotation.fields.supplier'))
                ->state(fn (PurchaseQuotation $record): string => $record->supplierLabel()),
            self::text('supplier_reference', __('purchase_quotation.fields.supplier_reference')),
            self::date('quote_date', __('purchase_quotation.fields.quote_date')),
            self::date('valid_until', __('purchase_quotation.fields.valid_until')),
            self::text('line_count', __('purchase_quotation.fields.line_count'))
                ->state(fn (PurchaseQuotation $record): int => (int) $record->getAttribute('line_count')),
            self::money('total_amount', __('purchase_quotation.fields.total')),
            self::text('total_try', __('purchase_quotation.fields.total_try'))
                ->state(fn (PurchaseQuotation $record): ?string => $record->totalInTry() !== null ? Money::format($record->totalInTry(), 'TRY') : null),
            self::text('status', __('purchase_quotation.fields.status')),
            self::text('order.order_no', __('purchase_quotation.fields.order')),
        ];
    }
}
