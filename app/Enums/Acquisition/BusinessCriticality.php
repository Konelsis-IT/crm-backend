<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BusinessCriticality: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Normal = 'normal';
    case Critical = 'critical';

    public function getColor(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::Critical => 'danger',
        };
    }
}
