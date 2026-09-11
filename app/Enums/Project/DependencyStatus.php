<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DependencyStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Waived = 'waived';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Waived => 'warning',
        };
    }
}
