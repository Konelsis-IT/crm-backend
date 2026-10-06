<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedArchive;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Baslangic zincirinin arsive isaretlenmesi (D-165, 6 Ekim 2026 kullanici
 * sorusu: "seed_archive'i dolduracaktik bir daha calismasin diye, niye bos?").
 *
 * Canlida dolu veritabaninda baslangic zinciri hic calismadigi icin arsiv
 * bos kalmisti; biri ileride eski bir seeder'i elle calistirsa canlida
 * silinmis bir firma ya da personeli yeniden acabilirdi. Bu seeder
 * DatabaseSeeder::START_CHAIN'i isaretleme kipinde calistirir: hicbir satir
 * yazilmaz, her satirin anahtari "islendi" olarak seed arsivine dusulur.
 * Satir disinda kalan bir yazma olursa diye her sey bir islem icinde yapilip
 * geri alinir; arsiv satirlari islemden sonra yazilir.
 *
 * Bir kez calisir (arsivdeki 'legacy-backfill' isaretiyle); deploy.sh her
 * kurulumda cagirsa da ikinci kez is yapmaz. Hata olursa kurulumu durdurmaz,
 * uyarir ve bir sonraki kurulumda yeniden dener.
 */
final class ArchiveLegacySeedsSeeder extends Seeder
{
    private const MARKER = 'legacy-backfill';

    public function run(): void
    {
        $archive = SeedArchive::instance();

        if (! $archive->ready()) {
            $this->command?->warn('Seed arsivi (B45) yok; baslangic zinciri isaretlenmedi.');

            return;
        }

        if ($archive->has(self::class, self::MARKER)) {
            $this->command?->info('Baslangic zinciri daha once arsive isaretlenmis; atlandi.');

            return;
        }

        $archive->beginBuffer();
        ProtectedSeeder::markOnly(true);
        $failed = null;

        DB::beginTransaction();

        try {
            $this->call(DatabaseSeeder::START_CHAIN);
        } catch (Throwable $exception) {
            $failed = $exception;
        } finally {
            DB::rollBack();
            ProtectedSeeder::markOnly(false);
        }

        $marked = $archive->flush();

        if ($failed !== null) {
            $this->command?->warn(sprintf('Baslangic zinciri isaretlenirken hata: %s. %d satir isaretlendi; kalanlar bir sonraki kurulumda yeniden denenecek.', $failed->getMessage(), $marked));

            return;
        }

        $archive->record(self::class, self::MARKER, null);
        $this->command?->info(sprintf('Baslangic zinciri arsive isaretlendi: %d satir (hicbir veri yazilmadi). Eski seed\'ler canlida artik hicbir satiri yeniden islemez.', $marked));
    }
}
