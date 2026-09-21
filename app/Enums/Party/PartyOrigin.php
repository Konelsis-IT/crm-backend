<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Firmanin kokeni (B33, D-107): yerli, Avrupa, Cin ya da diger yabanci.
 * Firma duzeyindedir; faaliyet satirindan bagimsizdir.
 */
enum PartyOrigin: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Domestic = 'domestic';
    case Europe = 'europe';
    case China = 'china';
    case Foreign = 'foreign';

    public function getColor(): string
    {
        return match ($this) {
            self::Domestic => 'success',
            self::Europe => 'info',
            self::China => 'warning',
            self::Foreign => 'gray',
        };
    }
}
