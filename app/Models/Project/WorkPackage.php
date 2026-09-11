<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\WorkPackageStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\WbsNode;
use App\Models\Project\WorkPackageDependency;
use App\Policies\WorkPackagePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('work_packages')]
#[Fillable([
    'project_workstream_id', 'project_id', 'package_code', 'name', 'description', 'wbs_node_id',
    'owner_personnel_id', 'status', 'planned_start_on', 'planned_finish_on',
])]
#[UsePolicy(WorkPackagePolicy::class)]
class WorkPackage extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkPackageStatus::class,
            'planned_start_on' => 'date',
            'planned_finish_on' => 'date',
        ];
    }

    public function workstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'project_workstream_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class, 'wbs_node_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function successorDependencies(): HasMany
    {
        return $this->hasMany(WorkPackageDependency::class, 'predecessor_package_id');
    }

    public function predecessorDependencies(): HasMany
    {
        return $this->hasMany(WorkPackageDependency::class, 'successor_package_id');
    }
}
