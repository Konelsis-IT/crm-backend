<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExposureStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Identified = 'identified';
    case Assessed = 'assessed';
    case Recovering = 'recovering';
    case Recovered = 'recovered';
    case WrittenOff = 'written_off';
    case Closed = 'closed';

    public function getColor(): string
    {
        return match ($this) {
            self::Identified => 'gray',
            self::Assessed => 'info',
            self::Recovering => 'warning',
            self::Recovered => 'success',
            self::WrittenOff => 'danger',
            self::Closed => 'gray',
        };
    }
}
