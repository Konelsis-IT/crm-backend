<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenderNoticeStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Captured = 'captured';
    case Screening = 'screening';
    case Pursuing = 'pursuing';
    case NotPursued = 'not_pursued';
    case Submitted = 'submitted';
    case Awarded = 'awarded';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Captured => 'gray',
            self::Screening => 'info',
            self::Pursuing => 'primary',
            self::NotPursued => 'gray',
            self::Submitted => 'warning',
            self::Awarded => 'success',
            self::Lost => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
