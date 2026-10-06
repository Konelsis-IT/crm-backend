<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Is dosyasinda secilebilen proje kapsam tipleri (B29, D-101). Bir is
 * dosyasinda birden fazla tip isaretlenebilir; her tip business_case_scopes
 * tablosunda bir satirdir. GES / RES / TM / HES'in kendi tutar alanlari
 * vardir (B30 ile HES eklendi).
 *
 * B43 (D-155, 5 Ekim 2026): proje tipi potansiyel iste secilir, tutarlar
 * teklif surumunun kapsamidir (proposal_version_scopes). BES ekranda "BESS"
 * adini tasir (deger `bes` kalir); BESS ve ENH/EIH'in de kendi alanlari vardir.
 * Tipin hangi teklif oncesi kontrol listesine baktigi checklistTemplate()'tedir.
 */
enum ProjectScopeType: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Ges = 'ges';
    case Res = 'res';
    case Tm = 'tm';
    case Hes = 'hes';
    case Bes = 'bes';
    case EnhEih = 'enh_eih';

    /**
     * Bu tipin kendi tutar alanlari var mi. B29'da yalniz GES / RES / TM / HES;
     * B43'ten beri teklif kapsaminda her tipin alanlari vardir.
     */
    public function hasFields(): bool
    {
        return match ($this) {
            self::Ges, self::Res, self::Tm, self::Hes, self::Bes, self::EnhEih => true,
        };
    }

    /**
     * Teklif oncesi kontrol listesi (D-155, kullanici karari): TM kendi
     * listesine, diger tipler GES listesine bakar.
     */
    public function checklistTemplate(): string
    {
        return $this === self::Tm ? 'tm' : 'ges';
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ges => 'warning',
            self::Res => 'info',
            self::Tm => 'primary',
            self::Hes => 'success',
            self::Bes => 'gray',
            self::EnhEih => 'gray',
        };
    }

    /**
     * Her proje tipinin tek simgesi (D-163, 6 Ekim 2026 kullanici talimati:
     * "her bir proje tipi icin bir icon belirleyelim, her yerde ayni iconu
     * kullanalim"). Liste rozetleri, kartlar, kapsam bolumleri ve secim
     * dugmeleri simgeyi buradan alir; baska yerde tipe simge yazilmaz.
     */
    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Ges => Heroicon::OutlinedSun,
            self::Res => Heroicon::OutlinedArrowPath,
            self::Tm => Heroicon::OutlinedBolt,
            self::Hes => Heroicon::OutlinedBeaker,
            self::Bes => Heroicon::OutlinedBattery100,
            self::EnhEih => Heroicon::OutlinedArrowsRightLeft,
        };
    }
}
