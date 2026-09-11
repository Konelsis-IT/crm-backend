<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrgUnitType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Company = 'company';
    case Division = 'division';
    case Department = 'department';
    case Section = 'section';
    case Office = 'office';
    case Site = 'site';
    case Committee = 'committee';

    public function getColor(): string
    {
        return match ($this) {
            self::Company => 'primary',
            self::Division => 'info',
            self::Department => 'success',
            self::Section => 'gray',
            self::Office => 'warning',
            self::Site => 'warning',
            self::Committee => 'gray',
        };
    }
}
