<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Negotiation = 'negotiation';
    case Signed = 'signed';
    case Active = 'active';
    case Completed = 'completed';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Negotiation => 'warning',
            self::Signed => 'success',
            self::Active => 'success',
            self::Completed => 'primary',
            self::Terminated => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
