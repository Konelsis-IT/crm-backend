<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClassificationCode: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';

    public function rank(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Internal => 1,
            self::Confidential => 2,
            self::Restricted => 3,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'gray',
            self::Internal => 'info',
            self::Confidential => 'warning',
            self::Restricted => 'danger',
        };
    }
}
