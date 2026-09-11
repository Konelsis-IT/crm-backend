<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClarificationType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case ScopeInterpretation = 'scope_interpretation';
    case AdditionalWork = 'additional_work';
    case PriceAdjustment = 'price_adjustment';
    case ContractQa = 'contract_qa';

    public function getColor(): string
    {
        return match ($this) {
            self::ScopeInterpretation => 'info',
            self::AdditionalWork => 'warning',
            self::PriceAdjustment => 'danger',
            self::ContractQa => 'gray',
        };
    }
}
