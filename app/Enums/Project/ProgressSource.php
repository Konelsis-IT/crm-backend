<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProgressSource: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Manual = 'manual';
    case Report = 'report';
    case Computed = 'computed';

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::Report => 'info',
            self::Computed => 'primary',
        };
    }
}
