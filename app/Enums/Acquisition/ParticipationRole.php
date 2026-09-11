<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ParticipationRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Host = 'host';
    case Attendee = 'attendee';
    case Presenter = 'presenter';

    public function getColor(): string
    {
        return match ($this) {
            self::Host => 'primary',
            self::Attendee => 'gray',
            self::Presenter => 'info',
        };
    }
}
