<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Enums\Platform\Feature;
use App\Models\Personnel\Personnel;
use App\Query\Personnel\OrganizationQueries;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;

/**
 * Genel bakista hangi departman panosu (D-212, 15 Ekim 2026 kullanici: "Simdi
 * bu mantikta bu tasarimi standart dashboard olarak ekleyelim. Bu eski
 * dashboard ekrani; ornegin proje departmaninda veya insan kaynaklari gibi
 * departmanlarda boyle gorulmeye devam edicek. Her departmana ozel dashboard
 * yaptikca o default dashboard yavas yavas kaybolur.").
 *
 * Ust yonetim ve tam yetkili kisi uc paneli birden gorur (Yonetici once);
 * departman calisani kendi departmaninin panelini. Paneli olmayan departman
 * (bos dizi) eski Genel bakisi gorur. Kisi adi yok; departman kodu ve gorev
 * (ExecutiveDirectory) belirler.
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
        private readonly RoleResolver $roles,
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

        if ($this->executives->isExecutive($personnel) || $this->roles->hasFullAccess($personnel)) {
            return array_values(array_filter(self::ALL, fn (string $panel): bool => $this->panelOpen($panel)));
        }

        // D-218: kisinin calistigi butun departmanlarin panelleri (ek gorevler dahil).
        $panels = [];

        foreach (app(OrganizationQueries::class)->unitCodesOf((int) $personnel->getKey()) as $code) {
            $panel = self::UNIT_PANELS[$code] ?? null;

            if ($panel !== null && $this->panelOpen($panel)) {
                $panels[] = $panel;
            }
        }

        return array_values(array_intersect(self::ALL, $panels));
    }

    /**
     * Satin alma paneli satin alma modulu acikken (D-215: pano 2.7'de, satin
     * alma 2.8'de acilir); kapaliyken Satin Alma calisani eski Genel bakisi,
     * ust yonetim satin alma panelsiz panoyu gorur.
     */
    public function purchasingOpen(): bool
    {
        return FeatureFlags::enabled(Feature::Procurement);
    }

    private function panelOpen(string $panel): bool
    {
        return $panel !== self::PURCHASING || $this->purchasingOpen();
    }
}
