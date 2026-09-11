<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MilestoneStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case AtRisk = 'at_risk';
    case Achieved = 'achieved';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::AtRisk => 'warning',
            self::Achieved => 'success',
            self::Missed => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
