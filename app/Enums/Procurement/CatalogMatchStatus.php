<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Excel satirinin katalog eslesmesi (D-187 kurali): matched var olan kaleme
 * baglandi, new katalogda yoktu ve bir kez eklendi, similar benzer kalem var,
 * secim bekliyor (secilene kadar satin almaya gonderilemez).
 */
enum CatalogMatchStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Matched = 'matched';
    case New = 'new';
    case Similar = 'similar';

    public function getColor(): string
    {
        return match ($this) {
            self::Matched => 'success',
            self::New => 'warning',
            self::Similar => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Matched => Heroicon::OutlinedLink,
            self::New => Heroicon::OutlinedSparkles,
            self::Similar => Heroicon::OutlinedQuestionMarkCircle,
        };
    }
}
