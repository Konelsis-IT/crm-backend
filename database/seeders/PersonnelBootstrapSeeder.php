<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\PersonnelStatus;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ilk yonetici personeli olusturur.
 */
class PersonnelBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $email = Personnel::normalizeEmail((string) config('konelsis.bootstrap_admin.email'));

        if ($email === null) {
            $this->command?->warn('KONELSIS_BOOTSTRAP_ADMIN_EMAIL tanimli degil; ilk yonetici personel olusturulmadi.');

            return;
        }

        if (Personnel::query()->where('normalized_email', $email)->exists()) {
            return;
        }

        $password = (string) config('konelsis.bootstrap_admin.password');
        $generated = false;

        if ($password === '') {
            $password = Str::password(16);
            $generated = true;
        }

        Personnel::query()->create([
            'full_name' => (string) config('konelsis.bootstrap_admin.name', 'Sistem Yoneticisi'),
            'email' => $email,
            'password' => $password,
            'locale' => (string) config('konelsis.organization.default_locale', 'tr'),
            'timezone' => (string) config('konelsis.organization.default_timezone', 'Europe/Istanbul'),
            'status' => PersonnelStatus::Active,
            'password_changed_at' => now(),
        ]);

        if ($generated) {
            $this->command?->warn("Ilk yonetici personel {$email} olusturuldu. Uretilen parola (bir kez gosterilir): {$password}");
        }

        $this->command?->info("Panele giris icin {$email} kaydina system_admin rolu RoleSeeder ile atanacak.");
    }
}
