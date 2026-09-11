<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PositionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Frozen => 'warning',
            self::Closed => 'gray',
        };
    }
}
