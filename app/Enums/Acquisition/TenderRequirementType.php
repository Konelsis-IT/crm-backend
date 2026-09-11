<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenderRequirementType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Eligibility = 'eligibility';
    case Technical = 'technical';
    case Financial = 'financial';
    case Document = 'document';
    case Legal = 'legal';
    case Experience = 'experience';

    public function getColor(): string
    {
        return match ($this) {
            self::Eligibility => 'primary',
            self::Technical => 'info',
            self::Financial => 'warning',
            self::Document => 'gray',
            self::Legal => 'danger',
            self::Experience => 'success',
        };
    }
}
