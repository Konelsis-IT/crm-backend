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
 * (`carried_from_item_id`).
 */
#[Table('report_items')]
#[Fillable(['report_id', 'sort_order', 'title', 'description', 'status', 'project_id', 'work_hours', 'due_on', 'carried_from_item_id'])]
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

    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_item_id');
    }
}
