<?php

declare(strict_types=1);

namespace App\Enums\Notification;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AlertState: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Acknowledged], true);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Acknowledged => 'warning',
            self::Resolved => 'success',
            self::Closed, self::Cancelled => 'gray',
        };
    }
}
