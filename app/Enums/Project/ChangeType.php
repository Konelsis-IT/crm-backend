<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChangeType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Scope = 'scope';
    case Schedule = 'schedule';
    case Cost = 'cost';
    case Technical = 'technical';
    case ContractVariation = 'contract_variation';

    public function getColor(): string
    {
        return match ($this) {
            self::Scope => 'primary',
            self::Schedule => 'info',
            self::Cost => 'warning',
            self::Technical => 'gray',
            self::ContractVariation => 'danger',
        };
    }
}
