<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Enums\Approval\DelegationScopeType;
use App\Enums\Approval\DelegationStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DelegationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vekalet (06 SS4.11): bir personel, bir yetkisini (simdilik onay karari)
 * belirli bir sure icin baskasina devreder. Vekilin verdigi karar,
 * delegation_snapshots ile o anki vekaletin kopyasini tasir.
 */
#[Table('delegations')]
#[Fillable([
    'grantor_personnel_id', 'delegate_personnel_id', 'capability_code', 'scope_type', 'scope_id', 'reason',
    'approved_by_personnel_id', 'status', 'valid_from', 'valid_until', 'revoked_at', 'revoked_by_personnel_id',
    'revoke_reason',
])]
#[UsePolicy(DelegationPolicy::class)]
class Delegation extends Model
{
    use HasAuditColumns;

    /** Onay karari verme yetkisi; simdilik tek yetki kodu. */
    public const CAPABILITY_APPROVAL_DECIDE = 'approval.decide';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => DelegationScopeType::class,
            'status' => DelegationStatus::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'grantor_personnel_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'delegate_personnel_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'revoked_by_personnel_id');
    }

    /** Su an gecerli mi (aktif ve tarih araliginda)? */
    public function isEffective(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return $this->status === DelegationStatus::Active
            && $this->valid_from->isBefore($at)
            && $this->valid_until->isAfter($at);
    }

    /** Verilen politika icin gecerli mi (kapsam "tumu" ya da o politika)? */
    public function coversPolicy(int $policyId): bool
    {
        return match ($this->scope_type) {
            DelegationScopeType::All => true,
            DelegationScopeType::ApprovalPolicy => (int) $this->scope_id === $policyId,
            default => false,
        };
    }

    public function scopeLabel(): string
    {
        return $this->scope_type->getLabel().($this->scope_id !== null ? ' #'.$this->scope_id : '');
    }
}
