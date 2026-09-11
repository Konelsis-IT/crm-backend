<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ObligationStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case Met = 'met';
    case Breached = 'breached';
    case Waived = 'waived';

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'gray',
            self::Met => 'success',
            self::Breached => 'danger',
            self::Waived => 'warning',
        };
    }
}
