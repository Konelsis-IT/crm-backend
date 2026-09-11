<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeviationType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Technical = 'technical';
    case Commercial = 'commercial';
    case Schedule = 'schedule';
    case Legal = 'legal';

    public function getColor(): string
    {
        return match ($this) {
            self::Technical => 'info',
            self::Commercial => 'warning',
            self::Schedule => 'gray',
            self::Legal => 'danger',
        };
    }
}
