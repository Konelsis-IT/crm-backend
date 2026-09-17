<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Is dosyasinda secilebilen proje kapsam tipleri (B29, D-101). Bir is
 * dosyasinda birden fazla tip isaretlenebilir; her tip business_case_scopes
 * tablosunda bir satirdir. GES / RES / TM / HES'in kendi tutar alanlari
 * vardir (B30 ile HES eklendi), BES / ENH-EIH alanlari kullanici bildirince
 * eklenecektir.
 */
enum ProjectScopeType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Ges = 'ges';
    case Res = 'res';
    case Tm = 'tm';
    case Hes = 'hes';
    case Bes = 'bes';
    case EnhEih = 'enh_eih';

    /** Bu tipin kendi tutar alanlari var mi (GES / RES / TM / HES). */
    public function hasFields(): bool
    {
        return match ($this) {
            self::Ges, self::Res, self::Tm, self::Hes => true,
            default => false,
        };
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
}
