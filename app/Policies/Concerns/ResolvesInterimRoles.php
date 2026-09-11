<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Personnel\Personnel;
use App\Services\Authorization\PermissionKey;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\SchemaReadiness;

/**
 * M01 politikalarinin ortak yardimcilari. Varsayilan reddir: her yetki
 * acikca verilir, listelenmeyen her sey false doner.
 *
 * 11 Eylul 2026 (kullanici karari, D-81): "Tum pozisyonlar icin ayri yetki
 * olmalidir." Her politika metodu artik system_admin/auditor kisayoluna ek
 * olarak Filament Shield izin anahtarina da bakar (permits()); boylece
 * Roller ekraninda pozisyon roluna isaretlenen kutu gercekten yetki verir.
 */
trait ResolvesInterimRoles
{
    protected function isSystemAdmin(Personnel $personnel): bool
    {
        return $personnel->isActive() && app(RoleResolver::class)->isSystemAdmin($personnel);
    }

    protected function isAuditor(Personnel $personnel): bool
    {
        return $personnel->isActive() && app(RoleResolver::class)->isAuditor($personnel);
    }

    protected function canRead(Personnel $personnel): bool
    {
        return $this->isSystemAdmin($personnel) || $this->isAuditor($personnel);
    }

    /**
     * Shield izin anahtari (orn. `ViewAny:Document`, `ChangeStatus:Personnel`)
     * bu personelin rollerinden birinde isaretli mi? Roller tablosu (B05)
     * uygulanmadan her zaman reddeder.
     */
    protected function permits(Personnel $personnel, string $ability): bool
    {
        if (! $personnel->isActive() || ! SchemaReadiness::hasBatch('B05')) {
            return false;
        }

        return $personnel->can(PermissionKey::for(static::class, $ability));
    }
}
