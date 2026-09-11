<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Tedarik kaleminin turu (11 SS1.11). */
enum SupplyItemKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Product = 'product';
    case Service = 'service';
    case Software = 'software';

    public function getColor(): string
    {
        return match ($this) {
            self::Product => 'primary',
            self::Service => 'info',
            self::Software => 'warning',
        };
    }
}
