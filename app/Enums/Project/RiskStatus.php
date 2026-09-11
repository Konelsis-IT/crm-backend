<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RiskStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Identified = 'identified';
    case Assessed = 'assessed';
    case Mitigating = 'mitigating';
    case Closed = 'closed';
    case Materialized = 'materialized';

    public function getColor(): string
    {
        return match ($this) {
            self::Identified => 'gray',
            self::Assessed => 'info',
            self::Mitigating => 'warning',
            self::Closed => 'success',
            self::Materialized => 'danger',
        };
    }
}
