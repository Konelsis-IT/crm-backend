<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BusinessCodeStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Historical = 'historical';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Historical => 'gray',
        };
    }
}
