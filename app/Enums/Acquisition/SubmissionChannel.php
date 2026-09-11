<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubmissionChannel: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Email = 'email';
    case Portal = 'portal';
    case HandDelivered = 'hand_delivered';
    case Courier = 'courier';

    public function getColor(): string
    {
        return match ($this) {
            self::Email => 'gray',
            self::Portal => 'info',
            self::HandDelivered => 'success',
            self::Courier => 'gray',
        };
    }
}
