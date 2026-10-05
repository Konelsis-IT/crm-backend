<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B42 - Ozellik surumleri ve yayin kaydi (D-151, 2 Ekim 2026 kullanici karari:
 * "Komple sistemdeki kodu ilgilendiren guncellemelerde direk gitsin ama geriye
 * kalan ozelliklerde features tablosuna bir versiyon eklesek ona gore
 * duzeltilse ...").
 *
 * - `features.version`: ozelligin yayinlandigi surum. Uygulama katalogdan
 *   (App\Enums\Platform\Feature::version()) yazar; elle degistirilmez.
 * - `feature_releases`: canlida yayinlanan surumlerin gecmisi. En son satir
 *   gecerli yayin surumudur; ondan buyuk surumdeki ozellikler gorunmez. Hic
 *   satir yoksa surum suzgeci yoktur (yerel ortam, ilk yayindan once canli).
 *   Satirlar yalniz eklenir (`php artisan konelsis:release`); geri almak icin
 *   onceki surum yeniden yayinlanir.
 *
 * Bir kereye mahsustur: sonraki yayinlar icin sema degismez.
 * On kosul: B39 (features), B00 (personnel).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table): void {
            $this->code($table, 'version', 16)->nullable()->after('decision_ref');
        });

        Schema::create('feature_releases', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'version', 16);
            $this->ts($table, 'published_at');
            $table->string('note', 500)->nullable();
            $this->auditCreated($table);

            $table->index('version', 'ix_feature_releases_version');
        });
        $this->personnelForeignKeys('feature_releases');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('feature_releases', function (Blueprint $table): void {
            $table->dropForeign($this->fkName('feature_releases', 'created_by_personnel_id'));
        });

        Schema::dropIfExists('feature_releases');

        Schema::table('features', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
