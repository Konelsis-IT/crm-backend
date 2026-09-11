<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrgUnitStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case Active = 'active';
    case Inactive = 'inactive';
    case Dissolved = 'dissolved';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Dissolved => 'danger',
        };
    }
}
