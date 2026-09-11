<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\DependencyStatus;
use App\Enums\Project\DependencyType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\ProjectWorkstream;
use App\Policies\WorkstreamDependencyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('workstream_dependencies')]
#[Fillable([
    'predecessor_workstream_id', 'successor_workstream_id', 'dependency_type', 'lag_days', 'is_hard', 'status',
    'waived_by_personnel_id', 'waiver_reason',
])]
#[UsePolicy(WorkstreamDependencyPolicy::class)]
class WorkstreamDependency extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dependency_type' => DependencyType::class,
            'lag_days' => 'integer',
            'is_hard' => 'boolean',
            'status' => DependencyStatus::class,
        ];
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'predecessor_workstream_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'successor_workstream_id');
    }

    public function waiver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'waived_by_personnel_id');
    }
}
