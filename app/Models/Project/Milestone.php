<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\MilestoneKind;
use App\Enums\Project\MilestoneStatus;
use App\Models\Acquisition\ContractMilestone;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\Project;
use App\Models\Project\WbsNode;
use App\Policies\MilestonePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('milestones')]
#[Fillable([
    'project_id', 'milestone_code', 'name', 'milestone_kind', 'wbs_node_id', 'contract_milestone_id', 'planned_at',
    'baseline_at', 'forecast_at', 'actual_at', 'status',
])]
#[UsePolicy(MilestonePolicy::class)]
class Milestone extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'milestone_kind' => MilestoneKind::class,
            'planned_at' => 'datetime',
            'baseline_at' => 'datetime',
            'forecast_at' => 'datetime',
            'actual_at' => 'datetime',
            'status' => MilestoneStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class, 'wbs_node_id');
    }

    public function contractMilestone(): BelongsTo
    {
        return $this->belongsTo(ContractMilestone::class, 'contract_milestone_id');
    }
}
