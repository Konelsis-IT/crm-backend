<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DelayStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case Mitigating = 'mitigating';
    case Absorbed = 'absorbed';
    case Claimed = 'claimed';
    case Closed = 'closed';

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Mitigating => 'info',
            self::Absorbed => 'gray',
            self::Claimed => 'primary',
            self::Closed => 'success',
        };
    }
}
