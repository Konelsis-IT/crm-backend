<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReviewDecision: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Returned = 'returned';

    public function getColor(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Returned => 'warning',
        };
    }
}
