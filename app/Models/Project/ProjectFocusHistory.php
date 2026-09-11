<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\FocusDirection;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectWorkstream;
use App\Policies\ProjectFocusHistoryPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('project_focus_histories')]
#[Fillable([
    'project_id', 'workstream_id', 'direction', 'started_at', 'ended_at', 'changed_by_personnel_id', 'reason',
])]
#[UsePolicy(ProjectFocusHistoryPolicy::class)]
class ProjectFocusHistory extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => FocusDirection::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'workstream_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'changed_by_personnel_id');
    }
}
