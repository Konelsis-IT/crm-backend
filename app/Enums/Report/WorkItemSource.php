<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Kartin kaynagi (B36, D-115): elle girildi ya da sistem hareketinden (oneri) olustu. */
enum WorkItemSource: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Manual = 'manual';
    case Automatic = 'automatic';

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::Automatic => 'success',
        };
    }
}
