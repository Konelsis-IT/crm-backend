<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Enums\Approval\DecisionRule;
use App\Enums\Approval\ResolverType;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ApprovalStepPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Politika surumundeki bir onay adimi (12 SS2.3): onayci nasil bulunur,
 * kac kisi karar vermeli, atlanabilir mi, vekalet alir mi, SLA'si nedir.
 */
#[Table('approval_steps')]
#[Fillable([
    'approval_policy_version_id', 'step_code', 'name_tr', 'name_en', 'sequence_no', 'resolver_type',
    'resolver_target_id', 'role_code', 'decision_rule', 'is_optional', 'allows_delegation', 'sla_minutes',
])]
#[UsePolicy(ApprovalStepPolicy::class)]
class ApprovalStep extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'resolver_type' => ResolverType::class,
            'decision_rule' => DecisionRule::class,
            'is_optional' => 'boolean',
            'allows_delegation' => 'boolean',
            'sla_minutes' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicyVersion::class, 'approval_policy_version_id');
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'en' ? (string) $this->name_en : (string) $this->name_tr;
    }
}
