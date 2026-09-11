<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\WorkstreamStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\OperationGroupDefinition;
use App\Models\Project\Project;
use App\Models\Project\WorkPackage;
use App\Models\Project\WorkstreamDependency;
use App\Policies\ProjectWorkstreamPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('project_workstreams')]
#[Fillable([
    'project_id', 'group_definition_id', 'owner_personnel_id', 'status', 'progress_pct', 'planned_start_on',
    'planned_finish_on', 'actual_start_on', 'actual_finish_on', 'blocked_at', 'block_reason',
])]
#[UsePolicy(ProjectWorkstreamPolicy::class)]
class ProjectWorkstream extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkstreamStatus::class,
            'progress_pct' => 'decimal:4',
            'planned_start_on' => 'date',
            'planned_finish_on' => 'date',
            'actual_start_on' => 'date',
            'actual_finish_on' => 'date',
            'blocked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OperationGroupDefinition::class, 'group_definition_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class, 'project_workstream_id');
    }

    public function successorDependencies(): HasMany
    {
        return $this->hasMany(WorkstreamDependency::class, 'predecessor_workstream_id');
    }

    public function predecessorDependencies(): HasMany
    {
        return $this->hasMany(WorkstreamDependency::class, 'successor_workstream_id');
    }
}
