<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Fiyat durumu (D-188, kullanici 10 Ekim 2026): Excel'de yesil satir fiyatindan
 * emin olunan (kesin), sari satir henuz net karar verilmemis (netlesmedi).
 */
enum PriceState: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Firm = 'firm';
    case Open = 'open';

    public function getColor(): string
    {
        return match ($this) {
            self::Firm => 'success',
            self::Open => 'warning',
        };
    }
}
