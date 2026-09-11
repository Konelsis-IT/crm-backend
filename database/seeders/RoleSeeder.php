<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Authorization\Role;
use App\Models\Personnel\Personnel;
use App\Services\Authorization\PositionRoleSync;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;

/**
 * Rol katalogu ve gecici e-posta listelerinden gercek role gecis
 * (D-16, D-64, D-65).
 *
 * Izinler artik burada degil Filament Shield ile uretilir/atanir
 * (php artisan shield:generate --all, sonra Roller ekranindan kutu
 * isaretlenir). system_admin, config/filament-shield.php'de super_admin
 * olarak tanimli oldugu icin zaten her izne otomatik sahiptir.
 *
 * KONELSIS_SYSTEM_ADMIN_EMAILS / KONELSIS_AUDITOR_EMAILS artik calisma
 * zamaninda okunmuyor; bu seeder yalniz bir kerelik gecis icin
 * auditor_emails listesini okur. PersonnelBootstrapSeeder'dan sonra
 * calismalidir.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $systemAdmin = Role::query()->firstOrCreate(['name' => RoleResolver::SYSTEM_ADMIN, 'guard_name' => 'web']);
        $auditor = Role::query()->firstOrCreate(['name' => RoleResolver::AUDITOR, 'guard_name' => 'web']);

        $bootstrapEmail = Personnel::normalizeEmail((string) config('konelsis.bootstrap_admin.email'));

        if ($bootstrapEmail !== null) {
            $admin = Personnel::query()->where('normalized_email', $bootstrapEmail)->first();
            $admin?->assignRole($systemAdmin);
        }

        $auditorEmails = array_filter(array_map(
            static fn (string $email): ?string => Personnel::normalizeEmail($email),
            explode(',', (string) config('konelsis.interim_roles.auditor_emails', '')),
        ));

        if ($auditorEmails !== []) {
            Personnel::query()
                ->whereIn('normalized_email', $auditorEmails)
                ->get()
                ->each(fn (Personnel $personnel) => $personnel->assignRole($auditor));
        }

        // Pozisyon rolleri (D-81): B03A uygulandiysa her pozisyon icin rol
        // uretilir ve acik atama sahiplerine verilir.
        if (SchemaReadiness::hasBatch('B03A')) {
            $result = app(PositionRoleSync::class)->syncAll();
            $this->command?->info(sprintf('%d pozisyon rolu esitlendi, %d personele rol verildi.', $result['positions'], $result['granted']));
        }
    }
}
