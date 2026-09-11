<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BidDecision: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Bid = 'bid';
    case NoBid = 'no_bid';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Bid => 'success',
            self::NoBid => 'danger',
        };
    }
}
