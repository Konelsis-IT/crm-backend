<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\SubmissionChannel;
use App\Models\Acquisition\BrandItem;
use App\Models\Acquisition\ComplianceItem;
use App\Models\Acquisition\Deviation;
use App\Models\Acquisition\EstimateVersion;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ResponsibilityMatrixItem;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Models\Reference\Currency;
use App\Policies\ProposalVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('proposal_versions')]
#[Fillable([
    'proposal_id', 'version_no', 'locale', 'status', 'version_hash', 'currency_code', 'total_price', 'margin_pct',
    'validity_until', 'is_critical_route', 'project_group_opinion_document_revision_id', 'summary',
    'prepared_by_personnel_id', 'approved_by_personnel_id', 'approved_at', 'submitted_at', 'submitted_channel',
    'submission_evidence_document_revision_id',
])]
#[UsePolicy(ProposalVersionPolicy::class)]
class ProposalVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'status' => ProposalVersionStatus::class,
            'total_price' => 'decimal:4',
            'margin_pct' => 'decimal:4',
            'validity_until' => 'date',
            'is_critical_route' => 'boolean',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'submitted_channel' => SubmissionChannel::class,
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function projectGroupOpinionRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'project_group_opinion_document_revision_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'prepared_by_personnel_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }

    public function submissionEvidenceRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'submission_evidence_document_revision_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProposalDocument::class, 'proposal_version_id');
    }

    public function complianceItems(): HasMany
    {
        return $this->hasMany(ComplianceItem::class, 'proposal_version_id');
    }

    public function deviations(): HasMany
    {
        return $this->hasMany(Deviation::class, 'proposal_version_id');
    }

    public function brandItems(): HasMany
    {
        return $this->hasMany(BrandItem::class, 'proposal_version_id');
    }

    public function responsibilityItems(): HasMany
    {
        return $this->hasMany(ResponsibilityMatrixItem::class, 'proposal_version_id');
    }

    public function estimateVersions(): HasMany
    {
        return $this->hasMany(EstimateVersion::class, 'proposal_version_id');
    }
}
