<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Personnel\Personnel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Gizli sistem hesabi (D-120, 23 Eylul 2026 kullanici karari): sirket
 * organizasyon semasinda yer almayan teknik yonetim hesabi. Arayuzde hic
 * gorunmez - personel listelerinde, secim kutularinda, "yazan / islem yapan"
 * alanlarinda ve gecmis dokumlerinde yoktur; varligi yalnizca veritabaninda
 * bilinir (`Personnel::hideSystemAccount` genel kapsami).
 *
 * Hesabin kim oldugu yapilandirmadan okunur (`konelsis.system_account.email`,
 * varsayilan SystemAccountSeeder::ADMIN_EMAIL); boylece canli ortamda farkli
 * bir teknik hesap kullanilacaksa kod degismeden ayarlanir.
 *
 * Bu hesap "personel degistir" (impersonate) yetkisine sahiptir; yetki
 * Roller ekranindan verilmez ve alinamaz (kodda sabittir).
 */
final class SystemAccount
{
    /** Istek suresince cozulen kimlik; false = henuz bakilmadi. */
    private static int | false | null $cachedId = false;

    public function email(): string
    {
        return (string) config('konelsis.system_account.email', 'admin@gmail.com');
    }

    /** Hesabin kimligi; kurulu degilse null. */
    public function id(): ?int
    {
        if (self::$cachedId !== false) {
            return self::$cachedId;
        }

        $id = Personnel::query()
            ->withoutGlobalScopes()
            ->where('normalized_email', Personnel::normalizeEmail($this->email()))
            ->value('id');

        return self::$cachedId = $id === null ? null : (int) $id;
    }

    public function is(Authenticatable | Personnel | int | null $user): bool
    {
        $id = $this->id();

        if ($id === null || $user === null) {
            return false;
        }

        $userId = $user instanceof Authenticatable ? (int) $user->getAuthIdentifier() : (int) $user;

        return $userId === $id;
    }

    /** Kayit gizli oldugu icin genel kapsam disinda okunur. */
    public function personnel(): ?Personnel
    {
        $id = $this->id();

        return $id === null ? null : Personnel::query()->withoutGlobalScopes()->find($id);
    }

    /** Test ve seed sonrasi onbellegi bosaltir. */
    public static function forget(): void
    {
        self::$cachedId = false;
    }
}
