<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Authorization\Role;
use App\Services\Authorization\RoleResolver;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedPositionRoles;

/**
 * Rol katalogu (D-16, D-64, D-65, D-89, D-90).
 *
 * Roller gercek is rolleridir: her pozisyonun kendi rolu vardir (D-81,
 * PositionRoleSync) ve bunlarin uzerine yalniz uc genel rol eklenir:
 * Yonetici, Gelistirici, Denetci. Teknik "system_admin" rolu 16 Eylul 2026
 * kullanici karariyla kaldirildi.
 *
 * Bu seeder yalniz rollerin VAR olmasini saglar; yetkiler ve rol sahipleri
 * RoleMatrixSeeder'dadir. Ortam degiskeninden rol dagitimi yoktur (D-89).
 *
 * D-165: korumali seeder. Her genel rol bir satirdir ('role:<ad>'); yalniz
 * eksikse acilir, islenince arsive duser (canlida silinen rol geri gelmez).
 * Pozisyon rolleri SeedPositionRoles ile: yalniz seed'in bu calismada ekledigi
 * pozisyon ve gorevler (syncAll canlida alinan rolu geri veriyordu).
 */
class RoleSeeder extends ProtectedSeeder
{
    public function run(): void
    {
        foreach ([RoleResolver::MANAGER, RoleResolver::DEVELOPER, RoleResolver::AUDITOR] as $name) {
            $this->row('role:'.$name, static fn (): Role => Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        // Pozisyon rolleri (D-81, D-165): yalniz seed'in ekledigi pozisyon ve gorevler.
        $result = SeedPositionRoles::sync();

        $this->command?->info(sprintf('%d pozisyon rolu esitlendi, %d personele rol verildi.', $result['positions'], $result['granted']));
    }
}
