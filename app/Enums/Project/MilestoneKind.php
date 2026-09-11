<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MilestoneKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Contractual = 'contractual';
    case Internal = 'internal';
    case Payment = 'payment';
    case Gate = 'gate';

    public function getColor(): string
    {
        return match ($this) {
            self::Contractual => 'primary',
            self::Internal => 'gray',
            self::Payment => 'warning',
            self::Gate => 'info',
        };
    }
}
