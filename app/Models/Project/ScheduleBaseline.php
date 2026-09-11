<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\BaselineSource;
use App\Enums\Project\BaselineStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\ScheduleBaselinePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('schedule_baselines')]
#[Fillable([
    'project_id', 'version_no', 'name', 'source', 'baseline_document_revision_id', 'planned_start_on',
    'planned_finish_on', 'status', 'approved_by_personnel_id', 'approved_at',
])]
#[UsePolicy(ScheduleBaselinePolicy::class)]
class ScheduleBaseline extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'source' => BaselineSource::class,
            'planned_start_on' => 'date',
            'planned_finish_on' => 'date',
            'status' => BaselineStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function baselineRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'baseline_document_revision_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }
}
