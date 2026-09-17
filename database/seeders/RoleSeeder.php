<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Authorization\Role;
use App\Services\Authorization\PositionRoleSync;
use App\Services\Authorization\RoleResolver;
use Illuminate\Database\Seeder;

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
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([RoleResolver::MANAGER, RoleResolver::DEVELOPER, RoleResolver::AUDITOR] as $name) {
            Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Pozisyon rolleri (D-81): her pozisyon icin rol uretilir ve acik
        // atama sahiplerine verilir.
        $result = app(PositionRoleSync::class)->syncAll();

        $this->command?->info(sprintf('%d pozisyon rolu esitlendi, %d personele rol verildi.', $result['positions'], $result['granted']));
    }
}
