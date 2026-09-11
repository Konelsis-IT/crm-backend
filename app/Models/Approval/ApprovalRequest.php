<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Approval\InvalidationReason;
use App\Enums\Approval\RequestStepStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\ApprovalRequestPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Onay talebi (12 SS2.4): hangi konu (subject_type/subject_id), hangi
 * politika surumu, hangi hash; adim satirlari ve kararlar altinda.
 */
#[Table('approval_requests')]
#[Fillable([
    'approval_policy_version_id', 'workflow_task_id', 'subject_type', 'subject_id', 'subject_revision_id',
    'subject_hash', 'subject_label', 'amount', 'currency_code', 'idempotency_key', 'personnel_id', 'note',
    'requested_at', 'current_step_sequence', 'status', 'decided_at', 'invalidation_reason',
])]
#[UsePolicy(ApprovalRequestPolicy::class)]
class ApprovalRequest extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'subject_revision_id' => 'integer',
            'amount' => 'decimal:4',
            'requested_at' => 'datetime',
            'current_step_sequence' => 'integer',
            'status' => ApprovalRequestStatus::class,
            'decided_at' => 'datetime',
            'invalidation_reason' => InvalidationReason::class,
        ];
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicyVersion::class, 'approval_policy_version_id');
    }

    /** Talep sahibi. */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class, 'approval_request_id')->orderBy('sequence_no')->orderBy('id');
    }

    public function decisions(): HasManyThrough
    {
        return $this->hasManyThrough(ApprovalDecision::class, ApprovalRequestStep::class, 'approval_request_id', 'approval_request_step_id');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /** Su an karar bekleyen (aktif) adim satirlari. */
    public function activeSteps(): HasMany
    {
        return $this->steps()->where('status', RequestStepStatus::Active->value);
    }
}
