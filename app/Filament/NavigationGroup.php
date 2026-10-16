<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

/**
 * Sol menu gruplari (D-70). Sira enum sirasidir: Raporlar (B36, D-115: Raporlar,
 * Is panosu, Isler, Kontrol matrisi), Analizler (D-116: Is raporlari; yalniz
 * ust yonetim), Is Alim, Operasyon - Proje, Operasyon - Satin alma, Idari,
 * Belgeler, Ayarlar.
 *
 * D-190 (12 Ekim 2026, kullanici: "Operasyon - Proje, Operasyon - Satin alma
 * seklinde gruplar olusturalim; satin alma icindeki menuleri icerisinde degil
 * operasyon satin alma menuleri altinda gosterelim ... bu menuler solda
 * gorulsun"): Proje Grubu ve Satin Alma kumeleri kalkti; ekranlari sol menude
 * kendi gruplarinin altinda. Ayarlar tek kume olarak son gruptur.
 */
enum NavigationGroup implements HasLabel
{
    case Reports;
    case Analytics;
    case Acquisition;
    case OperationsProject;
    case OperationsPurchasing;
    case Administrative;
    case Documents;
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Reports => __('app.nav.reports'),
            self::Analytics => __('app.nav.analytics'),
            self::Acquisition => __('app.nav.acquisition'),
            self::OperationsProject => __('app.nav.operations_project'),
            self::OperationsPurchasing => __('app.nav.operations_purchasing'),
            self::Administrative => __('app.nav.administrative'),
            self::Documents => __('app.nav.documents'),
            self::Settings => __('app.nav.settings'),
        };
    }
}
