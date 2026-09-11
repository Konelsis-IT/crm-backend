<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ProgressSource;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\ProgressSnapshotPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('progress_snapshots')]
#[Fillable([
    'project_id', 'snapshot_at', 'source', 'physical_progress_pct', 'planned_progress_pct', 'cost_progress_pct',
    'reported_by_personnel_id',
])]
#[UsePolicy(ProgressSnapshotPolicy::class)]
class ProgressSnapshot extends Model
{
    use AppendOnly, HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
            'source' => ProgressSource::class,
            'physical_progress_pct' => 'decimal:4',
            'planned_progress_pct' => 'decimal:4',
            'cost_progress_pct' => 'decimal:4',
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

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'reported_by_personnel_id');
    }
}
