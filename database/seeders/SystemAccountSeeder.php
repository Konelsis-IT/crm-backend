<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\PersonnelStatus;
use App\Models\Personnel\Personnel;
use App\Services\Authorization\RoleResolver;
use Illuminate\Database\Seeder;

/**
 * Sistem hesabi (D-91, 16 Eylul 2026 kullanici karari).
 *
 * Onceki `PersonnelBootstrapSeeder` ilk yoneticiyi ortam degiskeninden
 * (KONELSIS_BOOTSTRAP_ADMIN_*) okuyordu. Kullanici karariyla yetki yapisinin
 * tamami veritabaninda: ortam degiskeni yok, bootstrap kavrami yok. Tam
 * yetkili hesaplar burada kodda tanimlidir, rolleri RoleMatrixSeeder verir.
 *
 * Bu seeder yalniz sirket organizasyon semasinda yer almayan teknik yonetim
 * hesabini uretir; Yonetici ve Gelistirici gercek personel kayitlaridir ve
 * RealPersonnelSeeder tarafindan olusturulur.
 */
class SystemAccountSeeder extends Seeder
{
    /** Teknik yonetim hesabi (organizasyon semasinda yer almaz). */
    public const ADMIN_EMAIL = 'admin@gmail.com';

    /** Sirket yonetimi: tum ekranlar. */
    public const MANAGER_EMAIL = 'huseyin.gunes@konelsis.com';

    /** Gelistirme: tum ekranlar. */
    public const DEVELOPER_EMAIL = 'omer.ermis@konelsis.com';

    /** Ilk giris parolasi; ilk giristen sonra degistirilmelidir. */
    public const PASSWORD = 'Konelsis.Giris.2026';

    /**
     * Tam yetkili roller ve sahipleri.
     *
     * @return array<string, list<string>>
     */
    public static function fullAccess(): array
    {
        return [
            RoleResolver::MANAGER => [self::MANAGER_EMAIL],
            RoleResolver::DEVELOPER => [self::DEVELOPER_EMAIL, self::ADMIN_EMAIL],
        ];
    }

    /** Seed sirasinda islem sahibi olarak kullanilacak hesap. */
    public static function actor(): ?Personnel
    {
        foreach ([self::ADMIN_EMAIL, self::DEVELOPER_EMAIL, self::MANAGER_EMAIL] as $email) {
            $personnel = Personnel::query()->where('normalized_email', $email)->first();

            if ($personnel !== null) {
                return $personnel;
            }
        }

        return null;
    }

    public function run(): void
    {
        if (Personnel::query()->where('normalized_email', self::ADMIN_EMAIL)->exists()) {
            return;
        }

        $personnel = new Personnel([
            'full_name' => 'Sistem Yöneticisi',
            'email' => self::ADMIN_EMAIL,
            'password' => self::PASSWORD,
            'locale' => (string) config('konelsis.organization.default_locale', 'tr'),
            'timezone' => (string) config('konelsis.organization.default_timezone', 'Europe/Istanbul'),
            'status' => PersonnelStatus::Active,
        ]);
        $personnel->forceFill(['email_verified_at' => now(), 'password_changed_at' => now()]);
        $personnel->save();

        $this->command?->info(sprintf('Sistem hesabi %s olusturuldu (parola: %s).', self::ADMIN_EMAIL, self::PASSWORD));
    }
}
