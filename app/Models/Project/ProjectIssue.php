<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\IssueSeverity;
use App\Enums\Project\IssueStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectWorkstream;
use App\Policies\ProjectIssuePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('project_issues')]
#[Fillable([
    'project_id', 'workstream_id', 'issue_no', 'title', 'description', 'severity', 'status', 'owner_personnel_id',
    'raised_by_personnel_id', 'raised_at', 'due_at', 'resolved_at', 'resolution', 'source_type', 'source_id',
])]
#[UsePolicy(ProjectIssuePolicy::class)]
class ProjectIssue extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'raised_at' => 'datetime',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'raised_by_personnel_id');
    }
}
