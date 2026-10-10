<?php

declare(strict_types=1);

namespace App\Services\Acquisition\Concerns;

use Closure;

/**
 * Teklif surumunun yerinde duzeltilmesi (D-186, 9 Ekim 2026 kullanici karari:
 * "Eger kisi dogrudan teklif detayinda duzenleye basarsa o zaman istedigi
 * herhangi bir degisiklikte teklif surumu yukselmeyecektir ... surumleme isi
 * artik personeldedir").
 *
 * Surum cocuklari normalde yalniz taslak / incelemedeki surumde degisir (13
 * SS8.2, GuardsVersionChildren). Teklif "Duzenle", Dokumanlar'daki "Belge yukle"
 * ve belgeyi tekliften kaldirma guncel surumu durumu ne olursa olsun yerinde
 * degistirir. Bu sinif o izni yalniz verilen surum icin ve yalniz kapanis
 * suresince acar (AcquisitionIntakeService kullanir); baska surum ve baska
 * yol eski kurala tabidir.
 */
final class ProposalAmendment
{
    /** @var array<int, int> Izinli surum kimligi => ic ice cagri sayisi. */
    private static array $versions = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(int $versionId, Closure $callback): mixed
    {
        self::$versions[$versionId] = (self::$versions[$versionId] ?? 0) + 1;

        try {
            return $callback();
        } finally {
            self::$versions[$versionId]--;

            if (self::$versions[$versionId] <= 0) {
                unset(self::$versions[$versionId]);
            }
        }
    }

    public static function allows(int|string|null $versionId): bool
    {
        return $versionId !== null && isset(self::$versions[(int) $versionId]);
    }
}
