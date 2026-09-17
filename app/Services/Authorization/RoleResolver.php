<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Personnel\Personnel;

/**
 * Panel erisim rolu cozumu (M03, karar D-16, D-64, D-65, D-89, D-90).
 *
 * Roller gercek is rolleridir ve pozisyonlarla esittir (D-90): her pozisyonun
 * kendi rolu vardir (`PositionRoleSync`), yetkileri `RoleMatrixSeeder` ile
 * departmanina gore verilir. Teknik "system_admin" rolu kaldirildi; tam
 * yetki iki gercek rolde toplanir:
 *
 *  - Yonetici   : sirket yonetimi, tum ekranlar.
 *  - Gelistirici: sistemi gelistiren ekip, tum ekranlar.
 *
 * Ince taneli, ekran basina yetkiler Filament Shield'in urettigi izin
 * anahtarlariyla ($personnel->can('Islem:Kaynak')) kontrol edilir; bu sinif
 * yalniz "panele hic girebilir mi" ve "tam yetkili mi" sorularina bakar.
 * Yetki yalniz veritabanindan okunur, ortam degiskeni kullanilmaz (D-89).
 */
final class RoleResolver
{
    public const MANAGER = 'Yönetici';

    public const DEVELOPER = 'Geliştirici';

    public const AUDITOR = 'Denetçi';

    /** Tum ekranlari goren gercek roller. @var list<string> */
    public const FULL_ACCESS = [self::MANAGER, self::DEVELOPER];

    /** Tam yetkili rollerden birine sahip mi? */
    public function hasFullAccess(Personnel $personnel): bool
    {
        return $personnel->hasAnyRole(self::FULL_ACCESS);
    }

    public function isAuditor(Personnel $personnel): bool
    {
        return $personnel->hasRole(self::AUDITOR);
    }

    /**
     * En az bir rolu var mi? Pozisyon rolleri (D-81) dahil: rolu olan aktif
     * personel panele girer; ne gorecegi Shield izinleriyle belirlenir.
     */
    public function hasAnyRole(Personnel $personnel): bool
    {
        return $personnel->roles()->exists();
    }
}
