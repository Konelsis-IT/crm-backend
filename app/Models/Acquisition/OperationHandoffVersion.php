<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\HandoffVersionStatus;
use App\Models\Acquisition\ContractVersion;
use App\Models\Acquisition\HandoffItem;
use App\Models\Acquisition\HandoffReview;
use App\Models\Acquisition\OperationHandoff;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\OperationHandoffVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('operation_handoff_versions')]
#[Fillable([
    'operation_handoff_id', 'version_no', 'proposal_version_id', 'contract_version_id', 'baseline_snapshot',
    'snapshot_hash', 'manifest_document_revision_id', 'status', 'submitted_by_personnel_id', 'submitted_at',
    'decision_reason',
])]
#[UsePolicy(OperationHandoffVersionPolicy::class)]
class OperationHandoffVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'baseline_snapshot' => 'array',
            'status' => HandoffVersionStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function handoff(): BelongsTo
    {
        return $this->belongsTo(OperationHandoff::class, 'operation_handoff_id');
    }

    public function proposalVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }

    public function manifestRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'manifest_document_revision_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'submitted_by_personnel_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HandoffItem::class, 'handoff_version_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(HandoffReview::class, 'handoff_version_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class, 'accepted_handoff_version_id');
    }
}
