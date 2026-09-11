<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenderVersionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Current = 'current';
    case Superseded = 'superseded';

    public function getColor(): string
    {
        return match ($this) {
            self::Current => 'success',
            self::Superseded => 'gray',
        };
    }
}
