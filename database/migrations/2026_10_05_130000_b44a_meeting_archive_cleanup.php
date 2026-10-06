<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B44A - Gorusme arsivinin sadelestirilmesi (D-156 revizyonu, 5 Ekim 2026
 * kullanici karari: "archived_at ekle bitsin is bu kadar basit").
 *
 * B44'un ilk hali arsiv icin `archived_at` yanina `archived_by_personnel_id`
 * (FK), `archive_reason` ve `archived_at` indeksi de ekliyordu; bu hal yalniz
 * yerel veritabaninda uygulandi. B44 artik yalniz `archived_at` ekler. Bu adim
 * fazlaliklari, VARSA, kaldirir: B44'un sade halini uygulayan ortamda (canli)
 * hicbir sey yapmaz.
 *
 * On kosul: B44.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const TABLES = ['party_meeting_notes', 'meeting_plans'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            $index = $this->shorten('ix_'.$name.'_archived_at');
            $hasIndex = Schema::hasIndex($name, $index);
            $hasArchivedBy = Schema::hasColumn($name, 'archived_by_personnel_id');
            $hasReason = Schema::hasColumn($name, 'archive_reason');

            if (! $hasIndex && ! $hasArchivedBy && ! $hasReason) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name, $index, $hasIndex, $hasArchivedBy, $hasReason): void {
                if ($hasArchivedBy) {
                    $table->dropForeign($this->fkName($name, 'archived_by_personnel_id'));
                    $table->dropColumn('archived_by_personnel_id');
                }

                if ($hasReason) {
                    $table->dropColumn('archive_reason');
                }

                if ($hasIndex) {
                    $table->dropIndex($index);
                }
            });
        }
    }

    /** Kaldirilan fazlaliklar geri eklenmez (kullanici karari). */
    public function down(): void
    {
        $this->assertDestructiveAllowed();
    }
};
