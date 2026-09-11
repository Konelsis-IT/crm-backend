<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum CalendarDayKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case PublicHoliday = 'public_holiday';
    case ReligiousHoliday = 'religious_holiday';
    case CompanyHoliday = 'company_holiday';
    case HalfDay = 'half_day';
    case WorkingOverride = 'working_override';
}
