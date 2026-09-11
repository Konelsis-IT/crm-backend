<?php

declare(strict_types=1);

namespace App\Enums\Shared;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Generic [active, inactive] status used by reference tables.
 */
enum ActiveStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Inactive = 'inactive';

    public function getColor(): string
    {
        return $this === self::Active ? 'success' : 'gray';
    }
}
