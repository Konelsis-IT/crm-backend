<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ExposureKind;
use App\Enums\Project\ExposureStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\CbsNode;
use App\Models\Project\DelayEvent;
use App\Models\Project\Project;
use App\Models\Project\ProjectChange;
use App\Models\Reference\Currency;
use App\Policies\CommercialExposurePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('commercial_exposures')]
#[Fillable([
    'project_id', 'exposure_no', 'cbs_node_id', 'exposure_kind', 'description', 'exposure_amount', 'currency_code',
    'probability', 'owner_personnel_id', 'source_delay_event_id', 'source_change_id', 'status',
])]
#[UsePolicy(CommercialExposurePolicy::class)]
class CommercialExposure extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exposure_kind' => ExposureKind::class,
            'exposure_amount' => 'decimal:4',
            'probability' => 'decimal:6',
            'status' => ExposureStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function cbsNode(): BelongsTo
    {
        return $this->belongsTo(CbsNode::class, 'cbs_node_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function sourceDelayEvent(): BelongsTo
    {
        return $this->belongsTo(DelayEvent::class, 'source_delay_event_id');
    }

    public function sourceChange(): BelongsTo
    {
        return $this->belongsTo(ProjectChange::class, 'source_change_id');
    }
}
