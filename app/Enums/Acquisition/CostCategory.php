<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CostCategory: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Material = 'material';
    case Labor = 'labor';
    case Subcontract = 'subcontract';
    case Logistics = 'logistics';
    case Engineering = 'engineering';
    case Commissioning = 'commissioning';
    case Overhead = 'overhead';
    case Contingency = 'contingency';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Material => 'primary',
            self::Labor => 'info',
            self::Subcontract => 'info',
            self::Logistics => 'gray',
            self::Engineering => 'success',
            self::Commissioning => 'success',
            self::Overhead => 'gray',
            self::Contingency => 'warning',
            self::Other => 'gray',
        };
    }
}
