<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\BidDecision;
use App\Enums\Acquisition\OpportunityStage;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\OpportunityStageHistory;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\OpportunityPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('opportunities')]
#[Fillable([
    'business_case_id', 'stage', 'probability_pct', 'expected_value', 'expected_decision_on', 'market_code',
    'bid_decision', 'bid_decision_by_personnel_id', 'bid_decision_at', 'bid_decision_reason', 'competitor_note',
    'handoff_checklist_completed_at',
])]
#[UsePolicy(OpportunityPolicy::class)]
class Opportunity extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'probability_pct' => 'decimal:4',
            'expected_value' => 'decimal:4',
            'expected_decision_on' => 'date',
            'bid_decision' => BidDecision::class,
            'bid_decision_at' => 'datetime',
            'handoff_checklist_completed_at' => 'datetime',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function bidDecider(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'bid_decision_by_personnel_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(OpportunityStageHistory::class, 'opportunity_id');
    }
}
