<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\EstimateVersionStatus;
use App\Models\Acquisition\BoqItem;
use App\Models\Acquisition\EstimateLine;
use App\Models\Acquisition\PricingScenario;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Reference\Currency;
use App\Policies\EstimateVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('estimate_versions')]
#[Fillable([
    'proposal_version_id', 'version_no', 'currency_code', 'exchange_rate_snapshot', 'total_cost', 'total_price',
    'target_margin_pct', 'status', 'prepared_by_personnel_id', 'approved_by_personnel_id', 'approved_at', 'notes',
])]
#[UsePolicy(EstimateVersionPolicy::class)]
class EstimateVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'exchange_rate_snapshot' => 'array',
            'total_cost' => 'decimal:4',
            'total_price' => 'decimal:4',
            'target_margin_pct' => 'decimal:4',
            'status' => EstimateVersionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function proposalVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'prepared_by_personnel_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class, 'estimate_version_id');
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(PricingScenario::class, 'estimate_version_id');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(BoqItem::class, 'estimate_version_id');
    }
}
