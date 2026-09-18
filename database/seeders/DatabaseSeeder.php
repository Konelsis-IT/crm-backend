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
 * 18 Eylul 2026 (D-105, kullanici karari): kurgusal ornek seeder'lar
 * (PersonnelSampleDataSeeder, AcquisitionSampleDataSeeder,
 * DocumentSampleDataSeeder), ornek dokuman fixture'lari ve eski
 * PersonnelSnapshotSeeder depodan tamamen silindi. Bu dizinde yalniz
 * referans verisi, gercek organizasyon, gercek personel, rol/yetki matrisi
 * ve gercek taraflar kalir; kurgusal veri ureten seeder eklenmez.
 *
 * 18 Eylul 2026 (D-106, Sosyal Medya): zincirin sonuna kullanicinin
 * adlandirdigi iki gercek sosyal medya hesabi (SocialProfileSeeder) ve tarihi
 * sabit resmi ulusal gunler (SocialSpecialDaySeeder; herkese acik referans
 * verisi) eklendi. Ikisi de B31 uygulanmamissa kendini atlar, var olan satiri
 * degistirmez; baglanti, rakip hesap, kategori gibi sirket verisi uretmez.
 * Hesap sahibi gercek personelden bulundugu icin RoleMatrixSeeder'dan sonra
 * calisirlar.
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

            // Sosyal medya (B31, D-106): gercek hesaplar ve resmi ulusal gunler
            SocialProfileSeeder::class,
            SocialSpecialDaySeeder::class,
        ]);
    }
}
