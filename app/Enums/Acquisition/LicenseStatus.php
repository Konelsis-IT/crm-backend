<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Potansiyel isin "Proje durumu" (B43, D-155): lisanssiz uretim (5.1-C / 5.1-H
 * maddeleri) ya da lisansli surec (onlisans, lisans). Lisansli projede Cagri
 * mektubu zorunlu degildir (yuklenebilir).
 */
enum LicenseStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Unlicensed51C = 'unlicensed_5_1_c';
    case Unlicensed51H = 'unlicensed_5_1_h';
    case PreLicense = 'pre_license';
    case License = 'license';

    /** Lisansli surec (onlisans ya da lisans): Cagri mektubu zorunlu degil. */
    public function isLicensed(): bool
    {
        return in_array($this, [self::PreLicense, self::License], true);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unlicensed51C, self::Unlicensed51H => 'info',
            self::PreLicense => 'warning',
            self::License => 'success',
        };
    }
}
