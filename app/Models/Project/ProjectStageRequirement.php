<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\Applicability;
use App\Enums\Project\EvidenceType;
use App\Enums\Project\StageRequirementStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\StageEvidence;
use App\Models\Project\StageRequirementDefinition;
use App\Policies\ProjectStageRequirementPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('project_stage_requirements')]
#[Fillable([
    'project_stage_instance_id', 'requirement_definition_id', 'requirement_code_snapshot', 'name_snapshot_tr',
    'name_snapshot_en', 'evidence_type_snapshot', 'is_mandatory_snapshot', 'applicability', 'owner_personnel_id',
    'due_at', 'status', 'outcome_note',
])]
#[UsePolicy(ProjectStageRequirementPolicy::class)]
class ProjectStageRequirement extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence_type_snapshot' => EvidenceType::class,
            'is_mandatory_snapshot' => 'boolean',
            'applicability' => Applicability::class,
            'due_at' => 'datetime',
            'status' => StageRequirementStatus::class,
        ];
    }

    public function stageInstance(): BelongsTo
    {
        return $this->belongsTo(ProjectStageInstance::class, 'project_stage_instance_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(StageRequirementDefinition::class, 'requirement_definition_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(StageEvidence::class, 'project_stage_requirement_id');
    }
}
