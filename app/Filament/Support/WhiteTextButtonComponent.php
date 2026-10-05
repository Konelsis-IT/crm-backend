<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Support\Colors\Color;
use Filament\Support\View\Components\ButtonComponent;
use Filament\Support\View\Components\ColorMaps\ButtonComponentColorMap;

/**
 * Dolu dugmelerde yazi beyaz (D-149, 30 Eylul 2026 kullanici istegi:
 * "Butonlarin icindeki yazi beyaz olmaliydi, suan siyah okunmasi guc oluyor").
 *
 * Filament dugme zeminini beyaz yazinin okundugu tondan secer (WCAG AA) ama
 * yalniz 600 zemin + 500 ustune gelme tonunu dener; ikisi birden koyu degilse
 * acik zemin + koyu yaziya duser. Turuncu (Duzenle), zumrut (Yeni), yesil
 * (Kaydet / Onayla) ve warning (Onaya gonder) bu yuzden koyu yaziyla
 * ciziliyordu. Burada 600'den sonra 700 ve 800 de denenir: acik yazinin
 * okundugu ilk koyu ton secilir (turuncu-700, zumrut-700, yesil-700 ...).
 * Cerceveli (outlined) dugmeler Filament'in kuralinda kalir; gri dugmeler hic
 * boyanmaz (HasDefaultGrayColor).
 *
 * Filament dugme bileseni konteynerden cozer (ButtonComponent::make(),
 * ColorManager); AppServiceProvider bu sinifi baglar.
 */
final class WhiteTextButtonComponent extends ButtonComponent
{
    /**
     * @param  array<int, string>  $color
     * @return array<string, int>
     */
    public function getColorMap(array $color): array
    {
        if ($this->isOutlined) {
            return parent::getColorMap($color);
        }

        return ButtonComponentColorMap::make($color)
            ->minContrastRatio(Color::WCAG_AA_TEXT)
            ->lightBackground(bg: 600, hover: 500)
            ->lightBackground(bg: 700, hover: 600, alternateHover: 800)
            ->lightBackground(bg: 800, hover: 700, alternateHover: 900)
            ->darkBackground(bg: 600, hover: 500, alternateHover: 700)
            ->darkBackground(bg: 700, hover: 600, alternateHover: 800)
            ->darkBackground(bg: 800, hover: 700, alternateHover: 900)
            ->get();
    }
}
