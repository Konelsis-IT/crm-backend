<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BusinessOutcome: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'gray',
            self::Won => 'success',
            self::Lost => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
