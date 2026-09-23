<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Personnel\Personnel;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Authorization\PermissionKey;
use App\Services\Authorization\PermissionSubjects;
use App\Services\Authorization\RoleResolver;

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
    /** Yonetici / Gelistirici rolu: tum ekranlar (D-90). */
    protected function hasFullAccess(Personnel $personnel): bool
    {
        return $personnel->isActive() && app(RoleResolver::class)->hasFullAccess($personnel);
    }

    protected function isAuditor(Personnel $personnel): bool
    {
        return $personnel->isActive() && app(RoleResolver::class)->isAuditor($personnel);
    }

    protected function canRead(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->isAuditor($personnel);
    }

    /**
     * Ust yonetim (D-116): Yonetim kurulu baskani ve Idari mudur. Kisi adina
     * degil, pozisyon ve organizasyon birimine bakilir (ExecutiveDirectory).
     */
    protected function isExecutive(Personnel $personnel): bool
    {
        return app(ExecutiveDirectory::class)->isExecutive($personnel);
    }

    /**
     * Sirketin tamamini goren kisi: ust yonetim ya da tam yetkili rol
     * (Yonetici / Gelistirici, D-90).
     */
    protected function seesCompanyWide(Personnel $personnel): bool
    {
        return $this->isExecutive($personnel) || $this->hasFullAccess($personnel);
    }

    /**
     * Shield izin anahtari (orn. `ViewAny:Document`, `ChangeStatus:Personnel`)
     * bu personelin rollerinden birinde isaretli mi? Yetki yalniz veritabanindaki
     * rollerden gelir.
     *
     * Alt tablolarin (adres, taraf rolu, dokuman revizyonu, proje sorunu...)
     * kendi izin anahtari yoktur; bunlar ana kaydin yetkisini devralir
     * (D-93, PermissionSubjects).
     */
    protected function permits(Personnel $personnel, string $ability): bool
    {
        if (! $personnel->isActive()) {
            return false;
        }

        if ($personnel->can(PermissionKey::for(static::class, $ability))) {
            return true;
        }

        $parent = PermissionSubjects::parentOf(PermissionKey::subject(static::class));

        return $parent !== null && $personnel->can(PermissionKey::make($ability, $parent));
    }
}
