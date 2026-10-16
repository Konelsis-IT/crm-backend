<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\PurchaseOrderLinePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Siparis kalemi (D-192, B53): secili teklifin kalemlerinden kopyalanir
 * (opsiyon ve iptal haric). Fiyatlar siparis aninda donar; teslim alinan
 * miktar lojistikte isaretlenir.
 */
#[Table('purchase_order_lines')]
#[Fillable([
    'purchase_order_id', 'purchase_quotation_line_id', 'purchase_need_id', 'catalog_item_id', 'description',
    'brand_model', 'quantity', 'unit', 'unit_price', 'discount_rate', 'line_total', 'received_quantity', 'sort_order',
    // D-194 (B54): sabit birim
    'uom_id',
])]
#[UsePolicy(PurchaseOrderLinePolicy::class)]
class PurchaseOrderLine extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:6',
            'discount_rate' => 'decimal:4',
            'line_total' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotationLine::class, 'purchase_quotation_line_id');
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(PurchaseNeed::class, 'purchase_need_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /** Sabit birim (D-194, B54). */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** Miktarin tamami teslim alindi mi? */
    public function isFullyReceived(): bool
    {
        return (float) $this->getAttribute('received_quantity') >= (float) $this->getAttribute('quantity');
    }
}
