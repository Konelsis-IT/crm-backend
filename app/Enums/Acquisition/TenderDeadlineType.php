<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenderDeadlineType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Clarification = 'clarification';
    case SiteVisit = 'site_visit';
    case Submission = 'submission';
    case Opening = 'opening';
    case BondValidity = 'bond_validity';
    case Award = 'award';

    public function getColor(): string
    {
        return match ($this) {
            self::Clarification => 'gray',
            self::SiteVisit => 'info',
            self::Submission => 'danger',
            self::Opening => 'warning',
            self::BondValidity => 'gray',
            self::Award => 'success',
        };
    }
}
