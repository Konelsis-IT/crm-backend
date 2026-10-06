<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B45 - Seed arsivi (D-165, 6 Ekim 2026 kullanici karari: "seed icindeki
 * bilgi - satir adimi yazildikca arsive tasinsin ... boylece cozume kavusur";
 * "Veritabaninda satir arsivi" secildi).
 *
 * Seed dosyalarindaki her veri satiri (firma, kisi, potansiyel is, teklif,
 * gorusme plani, rol ...) yazildiginda buraya bir satir dusulur: hangi seeder,
 * satirin sabit anahtari (firma adi, e-posta, kod ...), yazilan kayit. Ayni
 * satir bir daha hic islenmez: canlida silinen ya da degistirilen kayit seed
 * ile geri gelmez; seed dosyasina sonradan eklenen satir yazilir. Arsiv her
 * ortamin kendi veritabanindadir (yerel ve canli ayri).
 *
 * Satirlar yalniz eklenir; uygulama bu tabloyu guncellemez, silmez.
 * Bir kereye mahsustur. On kosul: yok.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('seed_archive', function (Blueprint $table): void {
            $table->id();
            $this->ascii($table, 'seeder', 150);
            $this->asciiChar($table, 'row_hash', 64);
            $table->string('row_key', 500);
            $this->ascii($table, 'record_type', 150)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $this->ts($table, 'archived_at');

            $table->unique(['seeder', 'row_hash'], 'uq_seed_archive_seeder_row');
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('seed_archive');
    }
};
