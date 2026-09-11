<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LifecycleSegment: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Acquisition = 'acquisition';
    case Operation = 'operation';

    public function getColor(): string
    {
        return match ($this) {
            self::Acquisition => 'info',
            self::Operation => 'success',
        };
    }
}
