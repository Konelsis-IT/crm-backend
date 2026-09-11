<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Models\Acquisition\EstimateVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\PricingScenarioPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('pricing_scenarios')]
#[Fillable([
    'estimate_version_id', 'scenario_code', 'name', 'target_margin_pct', 'adjustment_pct', 'total_price',
    'is_selected',
])]
#[UsePolicy(PricingScenarioPolicy::class)]
class PricingScenario extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_margin_pct' => 'decimal:4',
            'adjustment_pct' => 'decimal:4',
            'total_price' => 'decimal:4',
            'is_selected' => 'boolean',
        ];
    }

    public function estimateVersion(): BelongsTo
    {
        return $this->belongsTo(EstimateVersion::class, 'estimate_version_id');
    }
}
