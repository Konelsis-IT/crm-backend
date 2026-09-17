<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Referans ve baslangic verisi. Her seeder sabit kodlara gore calisir ve
 * dogrulanmis sirket verisinin uzerine yazmaz. Yalniz yetkili DBA/DevOps
 * sureci calistirir.
 *
 * 16 Eylul 2026 (D-90, kullanici karari): kurgusal ornek veriler zincirden
 * cikarildi. Sistem artik yalniz referans verisi + gercek organizasyon +
 * gercek personel + rol/yetki matrisi ile kurulur; proje, teklif ve dokuman
 * kayitlari gercek kullanimla olusur. Firma takip listesindeki gercek
 * taraflar (RealPartySeeder, 16 Eylul 2026) zincirin sonunda yuklenir.
 *
 * Zincirden cikarilanlar (dosyalar duruyor, yerel denemede
 * `php artisan db:seed --class=...` ile tek tek calistirilabilir):
 * PersonnelSampleDataSeeder, AcquisitionSampleDataSeeder, DocumentSampleDataSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Kimlik ve referans
            SystemAccountSeeder::class,
            ReferenceDataSeeder::class,
            ReferenceTypeRegistrySeeder::class,
            DocumentTypeSeeder::class,

            // Organizasyon: once temel departmanlar, sonra gercek sema
            OrganizationStructureSeeder::class,
            RealOrganizationSeeder::class,

            // Katalog ve is kurallari
            ProjectCatalogSeeder::class,
            ApprovalPolicySeeder::class,

            // Gercek personel ve roller
            RoleSeeder::class,
            RealPersonnelSeeder::class,
            RoleMatrixSeeder::class,

            // Gercek taraf verisi (firma takip listesi, 16 Eylul 2026)
            RealPartySeeder::class,
        ]);
    }
}
