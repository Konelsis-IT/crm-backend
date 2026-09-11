<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Enums\Approval\ApprovalDecisionKind;
use App\Enums\Approval\DecisionChannel;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\ApprovalDecisionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Bir adim satirinda verilen karar (12 SS2.6). Yalniz eklenir; karar
 * anindaki konu hash'ini tasir.
 */
#[Table('approval_decisions')]
#[Fillable([
    'approval_request_step_id', 'personnel_id', 'on_behalf_of_personnel_id', 'decision', 'comment',
    'approved_subject_hash', 'channel', 'decided_at',
])]
#[UsePolicy(ApprovalDecisionPolicy::class)]
class ApprovalDecision extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecisionKind::class,
            'channel' => DecisionChannel::class,
            'decided_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function requestStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStep::class, 'approval_request_step_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'on_behalf_of_personnel_id');
    }

    public function delegationSnapshot(): HasOne
    {
        return $this->hasOne(DelegationSnapshot::class, 'approval_decision_id');
    }
}
