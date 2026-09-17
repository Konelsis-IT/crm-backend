<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Is dosyasinin teklif tipi (B29, D-101): butcesel ya da kat'i teklif.
 * Ekranda "Kritiklik" seciminin yerini alir.
 */
enum OfferType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Budgetary = 'budgetary';
    case Firm = 'firm';

    public function getColor(): string
    {
        return match ($this) {
            self::Budgetary => 'info',
            self::Firm => 'success',
        };
    }
}
