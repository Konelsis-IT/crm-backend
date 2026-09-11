<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenderAccessMode: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Manual = 'manual';
    case Email = 'email';
    case Api = 'api';

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::Email => 'info',
            self::Api => 'primary',
        };
    }
}
