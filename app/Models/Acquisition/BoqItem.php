<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Models\Acquisition\EstimateVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\BoqItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('boq_items')]
#[Fillable([
    'estimate_version_id', 'item_code', 'description', 'quantity', 'uom_id', 'unit_price', 'sort_order',
])]
#[UsePolicy(BoqItemPolicy::class)]
class BoqItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function estimateVersion(): BelongsTo
    {
        return $this->belongsTo(EstimateVersion::class, 'estimate_version_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
