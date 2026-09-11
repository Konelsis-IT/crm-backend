<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Organization = 'organization';
    case Person = 'person';

    public function getColor(): string
    {
        return match ($this) {
            self::Organization => 'primary',
            self::Person => 'info',
        };
    }
}
