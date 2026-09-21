<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B35 - Tablo disa aktarimi (D-110, 21 Eylul 2026 kullanici karari).
 *
 * Filament'in yerlesik Excel disa aktarimi (ExportAction) her disa aktarmayi
 * `exports` tablosuna yazar; dosya hazir olunca zil bildirimiyle indirme
 * baglantisi gelir. Yapi Filament'in kendi migration'iyla aynidir, tek fark:
 * kullanici tablosu `users` degil `personnel`dir (panelin kimligi personeldir).
 * Disa aktarma kuyruksuz (sync) calisir; dosyalar `local` (ozel) diskte durur,
 * indirme yalniz disa aktarmayi baslatan personele aciktir.
 *
 * On kosul: B00 (personnel), job_batches (Laravel kurulumunda var).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_disk');
            $table->string('file_name')->nullable();
            $table->string('exporter');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->index('user_id', 'ix_exports_user');
            $table->foreign('user_id', 'fk_exports_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('exports');
    }
};
