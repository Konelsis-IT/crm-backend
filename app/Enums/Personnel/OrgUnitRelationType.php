<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrgUnitRelationType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Hierarchy = 'hierarchy';
    case Functional = 'functional';
    case Matrix = 'matrix';

    public function getColor(): string
    {
        return match ($this) {
            self::Hierarchy => 'primary',
            self::Functional => 'info',
            self::Matrix => 'warning',
        };
    }
}
