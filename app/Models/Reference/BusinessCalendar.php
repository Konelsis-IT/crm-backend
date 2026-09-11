<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('business_calendars')]
#[Fillable(['code', 'name_tr', 'name_en', 'legal_entity_id', 'country_code', 'timezone', 'is_default', 'status'])]
class BusinessCalendar extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'status' => ActiveStatus::class,
        ];
    }


    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function weekdays(): HasMany
    {
        return $this->hasMany(BusinessCalendarWeekday::class, 'business_calendar_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(CalendarDay::class, 'business_calendar_id');
    }
}
