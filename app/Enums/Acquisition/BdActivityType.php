<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BdActivityType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Meeting = 'meeting';
    case Visit = 'visit';
    case Call = 'call';
    case Email = 'email';
    case Event = 'event';
    case SiteSurvey = 'site_survey';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Meeting => 'primary',
            self::Visit => 'info',
            self::Call => 'gray',
            self::Email => 'gray',
            self::Event => 'warning',
            self::SiteSurvey => 'success',
            self::Other => 'gray',
        };
    }
}
