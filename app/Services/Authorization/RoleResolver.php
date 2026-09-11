<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Personnel\Personnel;
use App\Services\Platform\SchemaReadiness;

/**
 * Panel erisim rolu cozumu (M03, karar D-16, D-64, D-65).
 *
 * Ince taneli, ekran basina yetkiler artik Filament Shield'in urettigi
 * Policy siniflarinda dogrudan $personnel->can('Islem:Kaynak') ile
 * kontrol edilir (bkz. 13 Policy sinifi, D-65). Bu sinif yalniz "panele
 * hic girebilir mi" sorusuna bakar (Personnel::canAccessPanel()); role
 * bagli, izne bagli degildir. system_admin, Shield'in super_admin
 * ayarinda (config/filament-shield.php) tanimli oldugu icin zaten her
 * izne otomatik sahiptir (Gate::before).
 *
 * B05 (roles) henuz uygulanmadiysa hasRole() sorgusu var olmayan
 * tablolara gider ve SQL hatasi ureterek girisi tamamen kilitler; bu
 * yuzden B05 teyit edilmeden varsayilan-deny doner.
 */
final class RoleResolver
{
    public const SYSTEM_ADMIN = 'system_admin';

    public const AUDITOR = 'auditor';

    public function isSystemAdmin(Personnel $personnel): bool
    {
        return SchemaReadiness::hasBatch('B05') && $personnel->hasRole(self::SYSTEM_ADMIN);
    }

    public function isAuditor(Personnel $personnel): bool
    {
        return SchemaReadiness::hasBatch('B05') && $personnel->hasRole(self::AUDITOR);
    }

    /**
     * En az bir rolu var mi? Pozisyon rolleri (D-81) dahil: rolu olan aktif
     * personel panele girer; ne gorecegi Shield izinleriyle belirlenir.
     */
    public function hasAnyRole(Personnel $personnel): bool
    {
        return SchemaReadiness::hasBatch('B05') && $personnel->roles()->exists();
    }
}
