<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BusinessCodeKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Offer = 'offer';
    case Project = 'project';

    public function getColor(): string
    {
        return match ($this) {
            self::Offer => 'info',
            self::Project => 'success',
        };
    }
}
