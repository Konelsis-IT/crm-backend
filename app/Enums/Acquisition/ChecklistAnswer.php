<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Teklif oncesi kontrol listesi alt maddesinin cevabi (B43, D-155). Excel'deki
 * ✓ / ✗ / – isaretlerinin karsiligi. Sicaklikta olumlu cevap puan getirir:
 * cogu soruda "Evet", olumsuz kurulmus sorularda "Hayir"
 * (ChecklistTemplates::isFavourable). "Bilinmiyor" eksik sayilir.
 */
enum ChecklistAnswer: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Yes = 'yes';
    case No = 'no';
    case Unknown = 'unknown';

    public function getColor(): string
    {
        return match ($this) {
            self::Yes => 'success',
            self::No => 'danger',
            self::Unknown => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Yes => Heroicon::OutlinedCheckCircle,
            self::No => Heroicon::OutlinedXCircle,
            self::Unknown => Heroicon::OutlinedMinusCircle,
        };
    }
}
