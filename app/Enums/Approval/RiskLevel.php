<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RiskLevel: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Normal = 'normal';
    case High = 'high';
    case Crisis = 'crisis';

    public function getColor(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::High => 'warning',
            self::Crisis => 'danger',
        };
    }
}
