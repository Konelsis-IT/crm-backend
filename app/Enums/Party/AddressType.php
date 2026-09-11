<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AddressType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Registered = 'registered';
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Site = 'site';
    case Office = 'office';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Registered => 'primary',
            self::Billing => 'info',
            self::Shipping => 'info',
            self::Site => 'warning',
            self::Office => 'gray',
            self::Other => 'gray',
        };
    }
}
