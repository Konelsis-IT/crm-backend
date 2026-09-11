<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\DecisionScope;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\ProjectDecisionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('project_decisions')]
#[Fillable([
    'project_id', 'decision_no', 'decision_scope', 'title', 'description', 'personnel_id', 'decided_at',
    'document_revision_id',
])]
#[UsePolicy(ProjectDecisionPolicy::class)]
class ProjectDecision extends Model
{
    use AppendOnly, HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision_scope' => DecisionScope::class,
            'decided_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }
}
