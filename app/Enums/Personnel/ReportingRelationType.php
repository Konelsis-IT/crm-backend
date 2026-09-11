<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportingRelationType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Line = 'line';
    case Functional = 'functional';
    case Project = 'project';

    public function getColor(): string
    {
        return match ($this) {
            self::Line => 'primary',
            self::Functional => 'info',
            self::Project => 'warning',
        };
    }
}
