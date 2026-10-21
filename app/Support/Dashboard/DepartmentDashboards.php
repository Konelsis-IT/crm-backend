<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Enums\Platform\Feature;
use App\Models\Personnel\Personnel;
use App\Query\Personnel\OrganizationQueries;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Authorization\SystemAccount;
use App\Services\Platform\FeatureFlags;

/**
 * Genel bakista hangi departman panosu (D-212, 15 Ekim 2026 kullanici: "Simdi
 * bu mantikta bu tasarimi standart dashboard olarak ekleyelim. Bu eski
 * dashboard ekrani; ornegin proje departmaninda veya insan kaynaklari gibi
 * departmanlarda boyle gorulmeye devam edicek. Her departmana ozel dashboard
 * yaptikca o default dashboard yavas yavas kaybolur.").
 *
 * D-221 (19 Ekim 2026 kullanici: "Bu dashboard'daki sekmeler hüseyin güneş
 * hesabında değil, sadece sistem yöneticisi hesabında aynı anda görülebilir
 * olacak. Her personel kendi panelini görecek buna yönetici de dahil"): panelleri
 * sekme olarak birlikte yalniz gizli sistem hesabi gorur. Ust yonetim yalniz
 * Yonetici panelini, departman calisani yalniz kendi departmaninin panelini
 * gorur (birden fazla departmanda calisan once kendi departmaninin panelini,
 * D-218); tam yetkili rol de kendi departmanina bakar. Paneli olmayan departman
 * (bos dizi) eski Genel bakisi gorur. Kisi adi yok; departman kodu, gorev
 * (ExecutiveDirectory) ve sistem hesabi (SystemAccount) belirler.
 */
final class DepartmentDashboards
{
    public const EXECUTIVE = 'executive';

    public const ACQUISITION = 'acquisition';

    public const PURCHASING = 'purchasing';

    /** Butun paneller, sekme sirasiyla. */
    public const ALL = [self::EXECUTIVE, self::ACQUISITION, self::PURCHASING];

    /** Departman kodu => paneli. Yeni departman panosu buraya eklenir. */
    private const UNIT_PANELS = [
        Departments::BUSINESS_DEVELOPMENT => self::ACQUISITION,
        Departments::PROPOSALS => self::ACQUISITION,
        Departments::PURCHASING => self::PURCHASING,
    ];

    public function __construct(
        private readonly ExecutiveDirectory $executives,
        private readonly SystemAccount $system,
    ) {}

    /** Ozellik acik ve kisinin bir paneli var mi (yoksa eski Genel bakis). */
    public function showsFor(?Personnel $personnel): bool
    {
        return FeatureFlags::enabled(Feature::DashboardDepartmentPanels) && $this->panelsFor($personnel) !== [];
    }

    /**
     * @return list<string>
     */
    public function panelsFor(?Personnel $personnel): array
    {
        if (! $personnel instanceof Personnel || ! $personnel->isActive()) {
            return [];
        }

        // D-221: butun paneller sekme olarak yalniz sistem hesabinda.
        if ($this->system->is($personnel)) {
            return array_values(array_filter(self::ALL, fn (string $panel): bool => $this->panelOpen($panel)));
        }

        if ($this->executives->isExecutive($personnel)) {
            return [self::EXECUTIVE];
        }

        // Kendi departmani once (D-218 ek gorevler sonra); ilk paneli olan departman.
        foreach (app(OrganizationQueries::class)->unitCodesOf((int) $personnel->getKey()) as $code) {
            $panel = self::UNIT_PANELS[$code] ?? null;

            if ($panel !== null && $this->panelOpen($panel)) {
                return [$panel];
            }
        }

        return [];
    }

    /**
     * Satin alma paneli satin alma modulu acikken (D-215: pano 2.7'de, satin
     * alma 2.8 / 2.9'da acilir); kapaliyken Satin Alma calisani eski Genel bakisi,
     * ust yonetim satin alma panelsiz panoyu gorur.
     */
    public function purchasingOpen(): bool
    {
        // D-229: ilk etapta (2.8) urunler, satin alma talepleri ve satin alinacaklar
        // acik; panel siparis seritleriyle birlikte siparislerle (2.9) gelir.
        return FeatureFlags::enabled(Feature::Procurement) && FeatureFlags::enabled(Feature::PurchaseOrders);
    }

    private function panelOpen(string $panel): bool
    {
        return $panel !== self::PURCHASING || $this->purchasingOpen();
    }
}
