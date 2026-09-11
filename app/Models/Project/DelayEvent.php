<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\DelayCauseCategory;
use App\Enums\Project\DelayStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\RecoveryAction;
use App\Policies\DelayEventPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('delay_events')]
#[Fillable([
    'project_id', 'workstream_id', 'detected_at', 'delay_days', 'cause_category', 'description', 'is_excusable',
    'evidence_document_revision_id', 'reported_by_personnel_id', 'status',
])]
#[UsePolicy(DelayEventPolicy::class)]
class DelayEvent extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'delay_days' => 'integer',
            'cause_category' => DelayCauseCategory::class,
            'is_excusable' => 'boolean',
            'status' => DelayStatus::class,
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

    public function evidenceRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'evidence_document_revision_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'reported_by_personnel_id');
    }

    public function recoveryActions(): HasMany
    {
        return $this->hasMany(RecoveryAction::class, 'delay_event_id');
    }
}
