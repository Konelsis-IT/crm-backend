<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CommunicationChannelType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Email = 'email';
    case Phone = 'phone';
    case Mobile = 'mobile';
    case Fax = 'fax';
    case Website = 'website';
    case Linkedin = 'linkedin';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Email => 'primary',
            self::Phone => 'info',
            self::Mobile => 'info',
            self::Fax => 'gray',
            self::Website => 'gray',
            self::Linkedin => 'gray',
            self::Other => 'gray',
        };
    }
}
