<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Enums\Approval\RequestStepStatus;
use App\Enums\Approval\ResolutionStatus;
use App\Enums\Approval\UnresolvedReason;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\ApprovalRequestStepPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Talebin bir adimindaki bir onayci satiri (12 SS2.5). Adimda birden fazla
 * onayci varsa her biri ayri satirdir; onayci bulunamadiysa personel bos,
 * resolution_status = unresolved.
 */
#[Table('approval_request_steps')]
#[Fillable([
    'approval_request_id', 'approval_step_id', 'sequence_no', 'personnel_id', 'resolved_role_snapshot',
    'resolution_status', 'unresolved_reason', 'status', 'activated_at', 'due_at', 'decided_at',
])]
#[UsePolicy(ApprovalRequestStepPolicy::class)]
class ApprovalRequestStep extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'resolution_status' => ResolutionStatus::class,
            'unresolved_reason' => UnresolvedReason::class,
            'status' => RequestStepStatus::class,
            'activated_at' => 'datetime',
            'due_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'approval_request_step_id');
    }

    public function isActive(): bool
    {
        return $this->status === RequestStepStatus::Active;
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->due_at !== null && $this->due_at->isPast();
    }
}
