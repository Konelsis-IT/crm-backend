<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RiskCategory: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Technical = 'technical';
    case Commercial = 'commercial';
    case Schedule = 'schedule';
    case Hse = 'hse';
    case Supply = 'supply';
    case Regulatory = 'regulatory';
    case Financial = 'financial';

    public function getColor(): string
    {
        return match ($this) {
            self::Technical => 'info',
            self::Commercial => 'warning',
            self::Schedule => 'gray',
            self::Hse => 'danger',
            self::Supply => 'info',
            self::Regulatory => 'gray',
            self::Financial => 'warning',
        };
    }
}
