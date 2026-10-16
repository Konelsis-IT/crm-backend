<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\CatalogMatchStatus;
use App\Enums\Procurement\CostLineType;
use App\Enums\Procurement\PriceState;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\CostListLinePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Maliyet listesi satiri (B52, D-189): Excel'deki kalem, katalog eslesmesi
 * ve fiyat durumu (yesil kesin / sari netlesmedi). Satin almaya gonderilince
 * bir siparis kalemi (purchaseNeed) olur; bir satir bir kez gider.
 */
#[Table('cost_list_lines')]
#[Fillable([
    'cost_list_id', 'line_type', 'excel_sheet', 'excel_row', 'section_no', 'section_name', 'group_name', 'line_no',
    'description', 'catalog_item_id', 'match_status', 'suggested_catalog_item_id', 'match_score', 'quantity', 'unit',
    'duration', 'unit_cost', 'currency_code', 'total_cost', 'total_sale', 'price_state', 'note', 'sort_order',
])]
#[UsePolicy(CostListLinePolicy::class)]
class CostListLine extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_type' => CostLineType::class,
            'match_status' => CatalogMatchStatus::class,
            'price_state' => PriceState::class,
            'excel_row' => 'integer',
            'match_score' => 'integer',
            'quantity' => 'decimal:4',
            'duration' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:4',
            'total_sale' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function costList(): BelongsTo
    {
        return $this->belongsTo(CostList::class, 'cost_list_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function suggestedItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'suggested_catalog_item_id');
    }

    public function purchaseNeed(): HasOne
    {
        return $this->hasOne(PurchaseNeed::class, 'cost_list_line_id');
    }

    /** "Insaat › Saha Duzeltme" bicimindeki Excel yolu. */
    public function path(): string
    {
        return implode(' › ', array_values(array_filter([
            $this->getAttribute('section_name'),
            $this->getAttribute('group_name'),
        ], static fn (mixed $part): bool => filled($part))));
    }

    public function hasCost(): bool
    {
        return (float) $this->getAttribute('total_cost') > 0;
    }
}
