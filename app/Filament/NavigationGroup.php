<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

/**
 * Sol menu gruplari (D-70). Sira enum sirasidir: Raporlar (B36, D-115: Raporlar,
 * Is panosu, Isler, Kontrol matrisi), Analizler (D-116: Is raporlari; yalniz
 * ust yonetim), Is Alim, Operasyon, Idari,
 * Belgeler, Ayarlar. Operasyon grubunun icinde departman kumeleri
 * (App\Filament\Clusters\ProjectGroup, Procurement, ...) yer alir; Ayarlar
 * tek kume olarak son gruptur.
 */
enum NavigationGroup implements HasLabel
{
    case Reports;
    case Analytics;
    case Acquisition;
    case Operations;
    case Administrative;
    case Documents;
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Reports => __('app.nav.reports'),
            self::Analytics => __('app.nav.analytics'),
            self::Acquisition => __('app.nav.acquisition'),
            self::Operations => __('app.nav.operations'),
            self::Administrative => __('app.nav.administrative'),
            self::Documents => __('app.nav.documents'),
            self::Settings => __('app.nav.settings'),
        };
    }
}
