<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ResponsiblePartyRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Konelsis = 'konelsis';
    case Customer = 'customer';
    case Subcontractor = 'subcontractor';
    case Supplier = 'supplier';
    case Shared = 'shared';

    public function getColor(): string
    {
        return match ($this) {
            self::Konelsis => 'primary',
            self::Customer => 'info',
            self::Subcontractor => 'gray',
            self::Supplier => 'gray',
            self::Shared => 'warning',
        };
    }
}
