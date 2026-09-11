<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TrainingAttendanceOutcome: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Registered = 'registered';
    case Attended = 'attended';
    case Passed = 'passed';
    case Failed = 'failed';
    case Absent = 'absent';

    public function getColor(): string
    {
        return match ($this) {
            self::Registered => 'gray',
            self::Attended => 'info',
            self::Passed => 'success',
            self::Failed => 'danger',
            self::Absent => 'warning',
        };
    }
}
