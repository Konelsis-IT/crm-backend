<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Referans ve baslangic verisi. Her seeder sabit kodlara gore calisir ve
 * dogrulanmis sirket verisinin uzerine yazmaz. Yalniz yetkili DBA/DevOps
 * sureci calistirir.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PersonnelBootstrapSeeder::class,
            RoleSeeder::class,
            ReferenceDataSeeder::class,
            OrganizationStructureSeeder::class,
            ReferenceTypeRegistrySeeder::class,
            ProjectCatalogSeeder::class,
            ApprovalPolicySeeder::class,
            DocumentSampleDataSeeder::class,
            AcquisitionSampleDataSeeder::class,
            PersonnelSampleDataSeeder::class,
        ]);
    }
}
