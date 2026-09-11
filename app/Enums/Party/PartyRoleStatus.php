<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyRoleStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Ended => 'gray',
        };
    }
}
