<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\CostCategory;
use App\Models\Acquisition\EstimateVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\EstimateLinePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('estimate_lines')]
#[Fillable([
    'estimate_version_id', 'parent_line_id', 'line_code', 'cost_type', 'description', 'quantity', 'uom_id',
    'unit_cost', 'unit_price', 'wbs_hint', 'sort_order',
])]
#[UsePolicy(EstimateLinePolicy::class)]
class EstimateLine extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_type' => CostCategory::class,
            'quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_total_cost' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function estimateVersion(): BelongsTo
    {
        return $this->belongsTo(EstimateVersion::class, 'estimate_version_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_line_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_line_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
