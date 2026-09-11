<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractDocumentRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case SignedContract = 'signed_contract';
    case Annex = 'annex';
    case Specification = 'specification';
    case Schedule = 'schedule';
    case Bond = 'bond';
    case Insurance = 'insurance';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::SignedContract => 'primary',
            self::Annex => 'gray',
            self::Specification => 'info',
            self::Schedule => 'gray',
            self::Bond => 'warning',
            self::Insurance => 'gray',
            self::Other => 'gray',
        };
    }
}
