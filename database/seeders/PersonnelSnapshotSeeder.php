<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Personnel\Competency;
use App\Models\Personnel\OrgUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sema sifirlamasi oncesi canli veritabanindaki personel kayitlarinin
 * (2026-09-07 10:19 itibariyle) anlik goruntusu. Parola alani zaten hash'li
 * degerin aynisidir, tekrar hash'lenmez. Organizasyon birimi ve yetkinlik
 * referanslari kod uzerinden cozulur; standart seed listesiyle (OrganizationStructureSeeder)
 * ayni siradadir, bu yuzden id kaymasindan etkilenmez.
 *
 * Kullanim: standart seed'den sonra elle calistirilir.
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=PersonnelSnapshotSeeder
 *
 * Bu dosya tek seferlik bir geri yukleme aracidir; DatabaseSeeder'a
 * otomatik olarak baglanmaz. Yeni bir anlik goruntu gerektiginde yeniden
 * uretilmelidir.
 */
class PersonnelSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $personnel = [
            [
                'full_name' => 'Ömer Faruk Ermiş',
                'email' => 'omer@gmail.com',
                'password_hash' => '$2y$12$MaSR4s3n9BSB2/sA0YmOROkojc2p1NAEaFKQ/N7nTeMlUMTq8yzhy',
                'personnel_no' => null,
                'national_id' => '11133344455',
                'phone' => '5535846263',
                'job_title' => 'Yapay Zeka ve Crm',
                'department_code' => 'YAZILIM',
                'photo_path' => 'personel-fotograflari/01M1RGDZTZNEXNS60Y71PVJ9PS.jpg',
                'hired_on' => '2026-08-31',
                'locale' => 'tr',
                'timezone' => 'Europe/Istanbul',
                'status' => 'active',
                'password_changed_at' => '2026-09-05 09:20:16.000000',
                'last_login_at' => null,
                'created_at' => '2026-09-05 08:21:32.000000',
                'updated_at' => '2026-09-05 10:04:39.000000',
            ],
            [
                'full_name' => 'Yeni personel',
                'email' => 'yeni@gmail.com',
                'password_hash' => '$2y$12$hEFRw5qDH7E2IxCe1GD1ieVjuV51kJssK9qmvKhipnIj0S2u7jA2G',
                'personnel_no' => null,
                'national_id' => null,
                'phone' => null,
                'job_title' => null,
                'department_code' => null,
                'photo_path' => null,
                'hired_on' => null,
                'locale' => 'tr',
                'timezone' => 'Europe/Istanbul',
                'status' => 'active',
                'password_changed_at' => null,
                'last_login_at' => null,
                'created_at' => '2026-09-05 10:13:58.000000',
                'updated_at' => '2026-09-05 10:13:58.000000',
            ],
        ];

        foreach ($personnel as $row) {
            $orgUnitId = $row['department_code'] !== null
                ? OrgUnit::query()->where('code', $row['department_code'])->value('id')
                : null;

            $normalizedEmail = strtolower(trim($row['email']));

            $attributes = [
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'normalized_email' => $normalizedEmail,
                'password' => $row['password_hash'],
                'personnel_no' => $row['personnel_no'],
                'national_id' => $row['national_id'],
                'phone' => $row['phone'],
                'job_title' => $row['job_title'],
                'org_unit_id' => $orgUnitId,
                'photo_path' => $row['photo_path'],
                'hired_on' => $row['hired_on'],
                'locale' => $row['locale'],
                'timezone' => $row['timezone'],
                'status' => $row['status'],
                'password_changed_at' => $row['password_changed_at'],
                'last_login_at' => $row['last_login_at'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];

            $existingId = DB::table('personnel')->where('normalized_email', $normalizedEmail)->value('id');

            if ($existingId !== null) {
                // PersonnelBootstrapSeeder KONELSIS_BOOTSTRAP_ADMIN_EMAIL icin zaten bir
                // satir olusturmus olabilir; o durumda kaydin uzerine yazilir, id korunur.
                DB::table('personnel')->where('id', $existingId)->update($attributes);

                continue;
            }

            DB::table('personnel')->insert($attributes + ['row_version' => 1]);
        }

        $pivots = [
        ];

        foreach ($pivots as $pivot) {
            $personnelId = DB::table('personnel')->where('normalized_email', strtolower(trim($pivot['personnel_email'])))->value('id');
            $competencyId = Competency::query()->where('code', $pivot['competency_code'])->value('id');

            if ($personnelId === null || $competencyId === null) {
                $this->command?->warn("Atlanan yetkinlik eslesmesi: {$pivot['personnel_email']} / {$pivot['competency_code']}");

                continue;
            }

            DB::table('personnel_competencies')->updateOrInsert(
                ['personnel_id' => $personnelId, 'competency_id' => $competencyId],
                ['level' => $pivot['level'], 'note' => $pivot['note'], 'row_version' => 1],
            );
        }

        $this->command?->info('Personel anlik goruntusu geri yuklendi ('.count($personnel).' kayit).');
    }
}
