<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IssueSeverity: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }
}
