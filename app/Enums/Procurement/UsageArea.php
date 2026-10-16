<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Urun / hizmetin ana kullanim yeri (D-190, B52A; kullanici: "Sirket ici /
 * Saha diye urunun ana kullanilacagi yer secilebilir ... urunde de gorunurken
 * sirket ici kisminda da gorunur"). Katalogda ve siparis kalemlerinde
 * "Sirket ici" ve "Saha" sekmeleri buna gore suzer; tur (Urun / Hizmet)
 * degismez. Tamamlanan Sirket ici kalem Sirket ici alimlar'a yazilir.
 */
enum UsageArea: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Office = 'office';
    case Field = 'field';

    public function getColor(): string
    {
        return match ($this) {
            self::Office => 'success',
            self::Field => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Office => Heroicon::OutlinedBuildingOffice2,
            self::Field => Heroicon::OutlinedMapPin,
        };
    }
}
