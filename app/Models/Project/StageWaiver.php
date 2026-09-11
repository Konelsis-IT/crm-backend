<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\ProjectStageRequirement;
use App\Policies\StageWaiverPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('stage_waivers')]
#[Fillable([
    'project_stage_instance_id', 'project_stage_requirement_id', 'approved_by_personnel_id',
    'risk_owner_personnel_id', 'reason', 'remediation_due_on', 'granted_at',
])]
#[UsePolicy(StageWaiverPolicy::class)]
class StageWaiver extends Model
{
    use AppendOnly, HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remediation_due_on' => 'date',
            'granted_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function stageInstance(): BelongsTo
    {
        return $this->belongsTo(ProjectStageInstance::class, 'project_stage_instance_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProjectStageRequirement::class, 'project_stage_requirement_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }

    public function riskOwner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'risk_owner_personnel_id');
    }
}
