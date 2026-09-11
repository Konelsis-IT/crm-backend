<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenderComplianceState: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Unknown = 'unknown';
    case Met = 'met';
    case PartiallyMet = 'partially_met';
    case NotMet = 'not_met';
    case Waived = 'waived';

    public function getColor(): string
    {
        return match ($this) {
            self::Unknown => 'gray',
            self::Met => 'success',
            self::PartiallyMet => 'warning',
            self::NotMet => 'danger',
            self::Waived => 'gray',
        };
    }
}
