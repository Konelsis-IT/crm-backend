<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Contract = 'contract';
    case Loi = 'loi';
    case Ntp = 'ntp';
    case Framework = 'framework';
    case Amendment = 'amendment';

    public function getColor(): string
    {
        return match ($this) {
            self::Contract => 'primary',
            self::Loi => 'info',
            self::Ntp => 'info',
            self::Framework => 'gray',
            self::Amendment => 'warning',
        };
    }
}
