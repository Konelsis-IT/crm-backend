<?php

declare(strict_types=1);

namespace App\Support\Dashboards;

use App\Enums\Platform\Feature;
use App\Models\Personnel\Personnel;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;

/**
 * Departman panolari (D-173, 8 Ekim 2026 kullanici istegi: "her departmanin
 * kendi dashboard'u olacak; ozel panosu olmayanlar icin mevcut standart pano
 * kalir; departman degisirse arayuz de degisir").
 *
 * Bugun yalniz UI Deneme'de onizlenir; kisiye hangi panonun dusecegi burada
 * tek yerden belirlenir (canliya alininca Genel bakis bu kurala bakacak):
 * - Yonetici: ust yonetim (ExecutiveDirectory: YKB, Idari mudur). D-147 ile
 *   ayni kural: sirket geneli tabloyu yalniz ust yonetim gorur; rol ya da
 *   izin bunu vermez.
 * - Teklif / Is Gelistirme: kisinin organizasyon birimi IS_GELISTIRME ya da TEKLIF.
 * - Genel: digerleri (mevcut standart Genel bakis degismez).
 */
final class DepartmentDashboards
{
    public const GENERAL = 'general';

    public const OFFER = 'offer';

    public const EXECUTIVE = 'executive';

    /** @var list<string> */
    public const MODES = [self::GENERAL, self::OFFER, self::EXECUTIVE];

    /** Teklif masasi panosunu alan birimlerin kodlari. */
    public const OFFER_UNITS = ['IS_GELISTIRME', 'TEKLIF'];

    public static function modeFor(?Personnel $personnel): string
    {
        if (! $personnel instanceof Personnel) {
            return self::GENERAL;
        }

        if (app(ExecutiveDirectory::class)->isExecutive($personnel)) {
            return self::EXECUTIVE;
        }

        $unit = $personnel->loadMissing('orgUnit')->orgUnit?->code;

        return in_array($unit, self::OFFER_UNITS, true) ? self::OFFER : self::GENERAL;
    }

    public static function normalize(?string $mode): ?string
    {
        return in_array($mode, self::MODES, true) ? $mode : null;
    }

    /**
     * UI Deneme onizlemesi (D-77 / D-91 ile ayni kural): gelistirme ortami,
     * ozellik acik, tam yetkili kisi. Sayfa ve disa aktarim ucu birlikte bakar.
     */
    public static function canPreview(?Personnel $personnel, Feature $feature = Feature::UiDashboards): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        return FeatureFlags::enabled($feature)
            && $personnel instanceof Personnel
            && $personnel->isActive()
            && app(RoleResolver::class)->hasFullAccess($personnel);
    }
}
