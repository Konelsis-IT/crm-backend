<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\PurchaseQuotationLinePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satin alma teklifinin kalemi (D-192, B53). Satin alinacaklar'daki bir kaleme
 * (`purchase_need_id`) ve katalog kalemine bagli olabilir; tedarikcinin
 * teklifte verdigi ek kalem (nakliye, montaj) ihtiyaca bagli olmayabilir.
 * `line_total` = miktar x birim fiyat x (1 - indirim %). Opsiyon ve iptal
 * isaretli kalem toplama girmez ve siparise gecmez. `ai_created_item`:
 * katalog kalemini Konelsis AI bu tekliften acti ("Konelsis AI olarak yeni
 * olusturdugum urunler" penceresinde gosterilir).
 */
#[Table('purchase_quotation_lines')]
#[Fillable([
    'purchase_quotation_id', 'purchase_need_id', 'catalog_item_id', 'description', 'brand_model', 'quantity', 'unit',
    'unit_price', 'discount_rate', 'line_total', 'is_option', 'is_cancelled', 'ai_created_item', 'note', 'sort_order',
    // D-194 (B54): sabit birim
    'uom_id',
])]
#[UsePolicy(PurchaseQuotationLinePolicy::class)]
class PurchaseQuotationLine extends Model
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
            'is_option' => 'boolean',
            'is_cancelled' => 'boolean',
            'ai_created_item' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotation::class, 'purchase_quotation_id');
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

    public function orderLine(): HasOne
    {
        return $this->hasOne(PurchaseOrderLine::class, 'purchase_quotation_line_id');
    }

    /** Toplama ve siparise giren kalem mi? */
    public function counts(): bool
    {
        return ! (bool) $this->getAttribute('is_option') && ! (bool) $this->getAttribute('is_cancelled');
    }

    /** Indirimsiz tutar (miktar x birim fiyat). */
    public function grossAmount(): float
    {
        return round((float) $this->getAttribute('quantity') * (float) $this->getAttribute('unit_price'), 4);
    }

    public static function computeTotal(float $quantity, float $unitPrice, float $discountRate): float
    {
        $rate = max(0.0, min(100.0, $discountRate));

        return round($quantity * $unitPrice * (1 - $rate / 100), 4);
    }
}
