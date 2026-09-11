<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CompetencyCategory: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Electrical = 'electrical';
    case Automation = 'automation';
    case Software = 'software';
    case Mechanical = 'mechanical';
    case Civil = 'civil';
    case Field = 'field';
    case Commercial = 'commercial';
    case Management = 'management';
    case Safety = 'safety';
    case Language = 'language';

    public function getColor(): string
    {
        return match ($this) {
            self::Electrical => 'warning',
            self::Automation => 'info',
            self::Software => 'primary',
            self::Mechanical => 'gray',
            self::Civil => 'gray',
            self::Field => 'success',
            self::Commercial => 'info',
            self::Management => 'primary',
            self::Safety => 'danger',
            self::Language => 'gray',
        };
    }
}
