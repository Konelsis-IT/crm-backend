<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Prospect = 'prospect';
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
    case Merged = 'merged';

    public function getColor(): string
    {
        return match ($this) {
            self::Prospect => 'gray',
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Blocked => 'danger',
            self::Merged => 'warning',
        };
    }
}
