<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Acquisition\HandoffStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\DepartmentHandoffVersion;
use App\Models\Project\Project;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\ProjectWorkstream;
use App\Policies\DepartmentHandoffPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('department_handoffs')]
#[Fillable([
    'project_id', 'source_workstream_id', 'target_workstream_id', 'trigger_stage_instance_id', 'status',
    'sla_due_at', 'accepted_version_id', 'accepted_by_personnel_id', 'accepted_at',
])]
#[UsePolicy(DepartmentHandoffPolicy::class)]
class DepartmentHandoff extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HandoffStatus::class,
            'sla_due_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function sourceWorkstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'source_workstream_id');
    }

    public function targetWorkstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'target_workstream_id');
    }

    public function triggerStageInstance(): BelongsTo
    {
        return $this->belongsTo(ProjectStageInstance::class, 'trigger_stage_instance_id');
    }

    public function acceptedVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentHandoffVersion::class, 'accepted_version_id');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'accepted_by_personnel_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DepartmentHandoffVersion::class, 'department_handoff_id');
    }
}
