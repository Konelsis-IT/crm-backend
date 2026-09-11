<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DelayCauseCategory: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Customer = 'customer';
    case Supplier = 'supplier';
    case Internal = 'internal';
    case Weather = 'weather';
    case Regulatory = 'regulatory';
    case Design = 'design';
    case ForceMajeure = 'force_majeure';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'warning',
            self::Supplier => 'warning',
            self::Internal => 'danger',
            self::Weather => 'gray',
            self::Regulatory => 'gray',
            self::Design => 'info',
            self::ForceMajeure => 'gray',
            self::Other => 'gray',
        };
    }
}
