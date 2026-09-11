<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\StageReviewDecision;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\ProjectStageInstance;
use App\Policies\StageReviewPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('stage_reviews')]
#[Fillable([
    'project_stage_instance_id', 'reviewer_personnel_id', 'decision', 'reviewed_hash', 'conditions', 'comment',
    'decided_at',
])]
#[UsePolicy(StageReviewPolicy::class)]
class StageReview extends Model
{
    use AppendOnly, HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => StageReviewDecision::class,
            'decided_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'reviewer_personnel_id');
    }
}
