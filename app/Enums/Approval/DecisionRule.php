<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Bir adimda birden fazla onayci varsa adimin ne zaman tamamlandigi.
 */
enum DecisionRule: string implements HasLabel
{
    use HasTranslatedLabel;

    case AnyOne = 'any_one';
    case All = 'all';
    case Majority = 'majority';
}
