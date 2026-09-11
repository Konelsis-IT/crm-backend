<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('business_calendar_weekdays')]
#[Fillable(['business_calendar_id', 'iso_weekday', 'work_start', 'work_end'])]
class BusinessCalendarWeekday extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iso_weekday' => 'integer',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }


    public function calendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class, 'business_calendar_id');
    }
}
