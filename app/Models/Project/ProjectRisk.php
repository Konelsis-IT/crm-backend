<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ResponseStrategy;
use App\Enums\Project\RiskCategory;
use App\Enums\Project\RiskStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectWorkstream;
use App\Policies\ProjectRiskPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('project_risks')]
#[Fillable([
    'project_id', 'workstream_id', 'risk_no', 'title', 'description', 'category', 'probability', 'impact',
    'response_strategy', 'mitigation_plan', 'owner_personnel_id', 'status', 'review_due_on',
])]
#[UsePolicy(ProjectRiskPolicy::class)]
class ProjectRisk extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => RiskCategory::class,
            'probability' => 'decimal:6',
            'impact' => 'integer',
            'score' => 'decimal:6',
            'response_strategy' => ResponseStrategy::class,
            'status' => RiskStatus::class,
            'review_due_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'workstream_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }
}
