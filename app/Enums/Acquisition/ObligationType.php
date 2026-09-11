<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ObligationType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Delivery = 'delivery';
    case Payment = 'payment';
    case Insurance = 'insurance';
    case Bond = 'bond';
    case Reporting = 'reporting';
    case Warranty = 'warranty';
    case Hse = 'hse';
    case Legal = 'legal';

    public function getColor(): string
    {
        return match ($this) {
            self::Delivery => 'primary',
            self::Payment => 'warning',
            self::Insurance => 'gray',
            self::Bond => 'gray',
            self::Reporting => 'info',
            self::Warranty => 'success',
            self::Hse => 'danger',
            self::Legal => 'danger',
        };
    }
}
