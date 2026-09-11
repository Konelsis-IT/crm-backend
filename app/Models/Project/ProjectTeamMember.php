<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\TeamMemberStatus;
use App\Enums\Project\TeamRole;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\ProjectTeamMemberPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Proje ekibi uyesi (11 SS1.12). */
#[Table('project_team_members')]
#[Fillable([
    'project_id', 'personnel_id', 'workstream_id', 'team_role', 'allocation_pct', 'assigned_from', 'assigned_until',
    'is_lead', 'status', 'note',
])]
#[UsePolicy(ProjectTeamMemberPolicy::class)]
class ProjectTeamMember extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'team_role' => TeamRole::class,
            'status' => TeamMemberStatus::class,
            'allocation_pct' => 'decimal:2',
            'assigned_from' => 'date',
            'assigned_until' => 'date',
            'is_lead' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function workstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'workstream_id');
    }
}
