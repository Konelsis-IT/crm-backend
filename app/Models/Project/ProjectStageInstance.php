<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\StageInstanceStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectStageRequirement;
use App\Models\Project\StageNode;
use App\Models\Project\StageReview;
use App\Models\Project\StageWaiver;
use App\Policies\ProjectStageInstancePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Table('project_stage_instances')]
#[Fillable([
    'project_id', 'stage_node_id', 'owner_personnel_id', 'status', 'entered_at', 'ready_at', 'passed_at',
    'condition_due_on',
])]
#[UsePolicy(ProjectStageInstancePolicy::class)]
class ProjectStageInstance extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StageInstanceStatus::class,
            'entered_at' => 'datetime',
            'ready_at' => 'datetime',
            'passed_at' => 'datetime',
            'condition_due_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function stageNode(): BelongsTo
    {
        return $this->belongsTo(StageNode::class, 'stage_node_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProjectStageRequirement::class, 'project_stage_instance_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(StageReview::class, 'project_stage_instance_id');
    }

    public function waivers(): HasMany
    {
        return $this->hasMany(StageWaiver::class, 'project_stage_instance_id');
    }

    /** Gate'in tum gereksinimlerine sunulan kanitlar. */
    public function evidence(): HasManyThrough
    {
        return $this->hasManyThrough(StageEvidence::class, ProjectStageRequirement::class, 'project_stage_instance_id', 'project_stage_requirement_id');
    }
}
