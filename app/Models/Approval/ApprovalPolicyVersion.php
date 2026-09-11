<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Enums\Approval\ApprovalMode;
use App\Enums\Approval\PolicyVersionStatus;
use App\Enums\Approval\RiskLevel;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\ApprovalPolicyVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Onay politikasinin bir surumu (12 SS2.2): mod, nisap, maker-checker,
 * tutar esigi, SLA ve adimlar. Yayimlandiktan sonra icerigi degismez.
 */
#[Table('approval_policy_versions')]
#[Fillable([
    'approval_policy_id', 'version_no', 'mode', 'quorum_count', 'requires_maker_checker', 'reapproval_on_change',
    'applies_min_amount', 'applies_max_amount', 'currency_code', 'risk_level', 'sla_minutes',
    'escalation_notification_rule_id', 'definition_hash', 'change_summary', 'status',
    'published_by_personnel_id', 'published_at',
])]
#[UsePolicy(ApprovalPolicyVersionPolicy::class)]
class ApprovalPolicyVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'mode' => ApprovalMode::class,
            'quorum_count' => 'integer',
            'requires_maker_checker' => 'boolean',
            'reapproval_on_change' => 'boolean',
            'applies_min_amount' => 'decimal:4',
            'applies_max_amount' => 'decimal:4',
            'risk_level' => RiskLevel::class,
            'sla_minutes' => 'integer',
            'status' => PolicyVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicy::class, 'approval_policy_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'approval_policy_version_id')->orderBy('sequence_no');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'published_by_personnel_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approval_policy_version_id');
    }

    public function isDraft(): bool
    {
        return $this->status === PolicyVersionStatus::Draft;
    }

    /** Surumun tutar esigi verilen tutari kapsiyor mu? Esik yoksa her tutari kapsar. */
    public function coversAmount(?float $amount): bool
    {
        if ($amount === null) {
            return $this->applies_min_amount === null && $this->applies_max_amount === null;
        }

        if ($this->applies_min_amount !== null && $amount < (float) $this->applies_min_amount) {
            return false;
        }

        return $this->applies_max_amount === null || $amount <= (float) $this->applies_max_amount;
    }
}
