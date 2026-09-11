<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExposureKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Loss = 'loss';
    case Penalty = 'penalty';
    case Claim = 'claim';
    case UnbilledWork = 'unbilled_work';
    case DisputedAmount = 'disputed_amount';

    public function getColor(): string
    {
        return match ($this) {
            self::Loss => 'danger',
            self::Penalty => 'danger',
            self::Claim => 'warning',
            self::UnbilledWork => 'info',
            self::DisputedAmount => 'warning',
        };
    }
}
