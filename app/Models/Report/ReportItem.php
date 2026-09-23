<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Enums\Report\ReportItemStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\Project;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pano is kalemi (B10A): gunluk/haftalik/aylik raporun "is panosu" satiri.
 * Tamamlanmayan kalem bir sonraki donemin raporuna tasinir
 * (`carried_from_item_id`). Is panosundan dondurulan kalem kaynak karti
 * (`work_item_id`) ve kapatilmis gune sonradan eklendiyse `is_late` tasir (B36).
 */
#[Table('report_items')]
#[Fillable(['report_id', 'sort_order', 'title', 'description', 'status', 'project_id', 'work_hours', 'due_on', 'carried_from_item_id', 'work_item_id', 'is_late'])]
class ReportItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportItemStatus::class,
            'sort_order' => 'integer',
            'work_hours' => 'decimal:2',
            'due_on' => 'date',
            'is_late' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** Dondurulan is panosu karti (B36, D-115). */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class, 'work_item_id');
    }

    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_item_id');
    }
}
