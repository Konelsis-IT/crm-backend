<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConsentStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Granted => 'success',
            self::Withdrawn => 'danger',
        };
    }
}
