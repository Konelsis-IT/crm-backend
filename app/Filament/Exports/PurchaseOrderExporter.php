<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Procurement\PurchaseOrder;
use App\Support\Money;

/** Siparisler listesi (PurchaseOrderResource) sutunlari (D-192). */
class PurchaseOrderExporter extends KonelsisExporter
{
    protected static ?string $model = PurchaseOrder::class;

    public static function fileLabel(): string
    {
        return __('purchase_order.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('order_no', __('purchase_order.fields.order_no')),
            self::text('supplier_label', __('purchase_order.fields.supplier'))
                ->state(fn (PurchaseOrder $record): string => $record->supplierLabel()),
            self::text('quotation.quotation_no', __('purchase_order.fields.quotation')),
            self::text('project_label', __('purchase_order.fields.project'))
                ->state(fn (PurchaseOrder $record): ?string => $record->project?->getAttribute('display_name')),
            self::text('line_count', __('purchase_order.fields.line_count'))
                ->state(fn (PurchaseOrder $record): int => (int) $record->getAttribute('line_count')),
            self::money('total_amount', __('purchase_order.fields.total')),
            self::text('total_try', __('purchase_order.fields.total_try'))
                ->state(fn (PurchaseOrder $record): ?string => $record->totalInTry() !== null ? Money::format($record->totalInTry(), 'TRY') : null),
            self::text('status', __('purchase_order.fields.status')),
            self::date('expected_delivery_on', __('purchase_order.fields.expected_delivery_on')),
            self::dateTime('ordered_at', __('purchase_order.fields.ordered_at')),
        ];
    }
}
