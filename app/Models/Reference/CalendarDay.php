<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Reference\CalendarDayKind;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('calendar_days')]
#[Fillable(['business_calendar_id', 'local_date', 'day_kind', 'is_working_day', 'name_tr', 'name_en', 'source_reference'])]
class CalendarDay extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'day_kind' => CalendarDayKind::class,
            'is_working_day' => 'boolean',
        ];
    }


    public function calendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class, 'business_calendar_id');
    }
}
