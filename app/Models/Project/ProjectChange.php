<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ChangeStatus;
use App\Enums\Project\ChangeType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Reference\Currency;
use App\Policies\ProjectChangePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('project_changes')]
#[Fillable([
    'project_id', 'change_no', 'change_type', 'title', 'description', 'personnel_id', 'requested_at',
    'source_type', 'source_id', 'impact_cost', 'currency_code', 'impact_days', 'affects_baseline', 'status',
    'approved_at',
])]
#[UsePolicy(ProjectChangePolicy::class)]
class ProjectChange extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_type' => ChangeType::class,
            'requested_at' => 'datetime',
            'impact_cost' => 'decimal:4',
            'impact_days' => 'integer',
            'affects_baseline' => 'boolean',
            'status' => ChangeStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
