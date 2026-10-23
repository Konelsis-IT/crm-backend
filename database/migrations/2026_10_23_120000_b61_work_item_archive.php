<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B61 - Is arsivi (D-240, 23 Ekim 2026 kullanici talimati: "Isler kisminda isi
 * arsivleme ozelligi eksik onu getirelim"; D-156 kurali: yalniz `archived_at`).
 *
 * Yeni tablo yoktur. `work_items` tablosuna yalniz `archived_at` (bos = aktif)
 * eklenir; kimin arsivledigi isin hareket kaydinda (work_item.archived) durur.
 * Arsivdeki is panoda, listelerde ve raporlarda varsayilan olarak gorunmez;
 * Isler listesindeki Arsiv suzgeciyle gosterilir ve geri alinir.
 *
 * On kosul: B36 (work_items).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        if (Schema::hasColumn('work_items', 'archived_at')) {
            return;
        }

        Schema::table('work_items', function (Blueprint $table): void {
            $this->ts($table, 'archived_at')->nullable();
            $table->index('archived_at', 'ix_work_items_archived_at');
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('work_items', function (Blueprint $table): void {
            $table->dropIndex('ix_work_items_archived_at');
            $table->dropColumn('archived_at');
        });
    }
};
