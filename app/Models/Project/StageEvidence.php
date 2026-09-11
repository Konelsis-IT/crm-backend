<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Models\Project\ProjectStageRequirement;
use App\Policies\StageEvidencePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('stage_evidence')]
#[Fillable([
    'project_stage_requirement_id', 'document_revision_id', 'evidence_hash', 'submitted_by_personnel_id',
    'submitted_at', 'accepted_by_personnel_id', 'accepted_at',
])]
#[UsePolicy(StageEvidencePolicy::class)]
class StageEvidence extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProjectStageRequirement::class, 'project_stage_requirement_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'submitted_by_personnel_id');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'accepted_by_personnel_id');
    }
}
