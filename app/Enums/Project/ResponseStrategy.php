<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ResponseStrategy: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Avoid = 'avoid';
    case Mitigate = 'mitigate';
    case Transfer = 'transfer';
    case Accept = 'accept';

    public function getColor(): string
    {
        return match ($this) {
            self::Avoid => 'danger',
            self::Mitigate => 'warning',
            self::Transfer => 'info',
            self::Accept => 'gray',
        };
    }
}
