<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Gorusme notunun kanali (B28, D-98): gorusme nasil yapildi?
 */
enum MeetingChannel: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Visit = 'visit';
    case Phone = 'phone';
    case Email = 'email';
    case Message = 'message';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Visit => 'success',
            self::Phone => 'info',
            self::Email => 'primary',
            self::Message => 'gray',
            self::Other => 'gray',
        };
    }
}
