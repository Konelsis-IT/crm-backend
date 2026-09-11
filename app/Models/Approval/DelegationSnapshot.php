<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DelegationSnapshotPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vekaletle verilen kararin, karar anindaki vekalet kopyasi (12 SS2.7).
 */
#[Table('delegation_snapshots')]
#[Fillable([
    'approval_decision_id', 'source_delegation_id', 'grantor_personnel_id', 'delegate_personnel_id',
    'scope_snapshot', 'valid_from_snapshot', 'valid_until_snapshot',
])]
#[UsePolicy(DelegationSnapshotPolicy::class)]
class DelegationSnapshot extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from_snapshot' => 'datetime',
            'valid_until_snapshot' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(ApprovalDecision::class, 'approval_decision_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class, 'source_delegation_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'grantor_personnel_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'delegate_personnel_id');
    }
}
