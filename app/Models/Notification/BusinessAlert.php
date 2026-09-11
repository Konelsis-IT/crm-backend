<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Enums\Notification\AlertSeverity;
use App\Enums\Notification\AlertState;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\BusinessAlertPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kritik is uyarisi (07 SS5.4 alt kumesi, B11A): yaklasan/gecmis son tarih.
 * Basligi `title_key` + `params` ile o anki dilde uretilir.
 */
#[Table('business_alerts')]
#[Fillable([
    'alert_no', 'trigger_code', 'subject_type', 'subject_id', 'project_id', 'org_unit_id', 'severity',
    'owner_personnel_id', 'title_key', 'params', 'url', 'due_at', 'state', 'opened_at',
    'acknowledged_at', 'acknowledged_by_personnel_id', 'closed_at', 'dedupe_key',
])]
#[UsePolicy(BusinessAlertPolicy::class)]
class BusinessAlert extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'state' => AlertState::class,
            'params' => 'array',
            'subject_id' => 'integer',
            'due_at' => 'datetime',
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'acknowledged_by_personnel_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    /** O anki dilde baslik. */
    public function title(): string
    {
        return __($this->title_key, (array) ($this->params ?? []));
    }

    public function isOpen(): bool
    {
        return $this->state->isOpen();
    }
}
