<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DecisionScope: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Gate = 'gate';
    case Change = 'change';
    case Commercial = 'commercial';
    case Technical = 'technical';
    case Focus = 'focus';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Gate => 'info',
            self::Change => 'warning',
            self::Commercial => 'primary',
            self::Technical => 'gray',
            self::Focus => 'success',
            self::Other => 'gray',
        };
    }
}
