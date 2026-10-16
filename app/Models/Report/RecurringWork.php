<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Enums\Report\RecurrenceFrequency;
use App\Enums\Report\RecurringWorkKind;
use App\Enums\Report\WorkItemPriority;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\RecurringWorkPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gorev ve sorumluluk: tekrarli is tanimi (D-219, B57; kullanici 16 Ekim 2026:
 * "Rapor Kontrol sisteme sabitli tekrarlı iş olarak işleyecek, sisteme her
 * personel için tekrarlı iş sistemi eklemeliyiz. Görev ve sorumluluklar gibi
 * düşünebiliriz. Uygun periyotlarda sürekli yapıp yapmadığı kontrolü
 * sağlanabilecek").
 *
 * Her donem (gunluk / haftalik / aylik) sorumlunun panosuna bir is acilir
 * (`work_items.recurring_work_id` + `period_key`); donem bitince tamamlanmamis
 * is "yapılmadı" sayilir. Tanim silinmez, arsivlenir (D-156).
 */
#[Table('recurring_works')]
#[Fillable([
    'title', 'description', 'kind', 'frequency', 'weekday', 'month_day', 'personnel_id', 'org_unit_id', 'project_id',
    'category_code', 'priority', 'is_active', 'starts_on', 'ends_on', 'archived_at',
])]
#[UsePolicy(RecurringWorkPolicy::class)]
class RecurringWork extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RecurringWorkKind::class,
            'frequency' => RecurrenceFrequency::class,
            'priority' => WorkItemPriority::class,
            'weekday' => 'integer',
            'month_day' => 'integer',
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** Donemlerin isleri (en yeni once). */
    public function occurrences(): HasMany
    {
        return $this->hasMany(WorkItem::class, 'recurring_work_id')->orderByDesc('work_on')->orderByDesc('id');
    }

    /** @param  Builder<RecurringWork>  $query */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('archived_at'));
    }
}
