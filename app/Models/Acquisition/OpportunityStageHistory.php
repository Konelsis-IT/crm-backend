<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\OpportunityStage;
use App\Models\Acquisition\Opportunity;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\OpportunityStageHistoryPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('opportunity_stage_histories')]
#[Fillable(['opportunity_id', 'from_stage', 'to_stage', 'changed_by_personnel_id', 'changed_at', 'reason'])]
#[UsePolicy(OpportunityStageHistoryPolicy::class)]
class OpportunityStageHistory extends Model
{
    use AppendOnly, HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_stage' => OpportunityStage::class,
            'to_stage' => OpportunityStage::class,
            'changed_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'changed_by_personnel_id');
    }
}
