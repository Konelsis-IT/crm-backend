<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B44 - Gorusme notu ve gorusme plani arsivi (D-156, 5 Ekim 2026 kullanici
 * karari: "Projede silme islemi yok dedik ama arsive alinabilmeli ... filtre ile
 * arsivdekileri goster secilir"; ayni gun: "archived_at ekle bitsin").
 *
 * Yeni tablo yoktur. `party_meeting_notes` ve `meeting_plans` tablolarina yalniz
 * `archived_at` (bos = aktif) eklenir. Kimin arsivledigi Personel Hareketleri'nde
 * (party_meeting_note.archived / meeting_plan.archived) durur.
 *
 * Not arsive alininca ondan dogan plan satirlari ayni anla arsivlenir;
 * arsivden cikarinca birlikte doner (uygulama kurali, MeetingPlanService).
 *
 * On kosul: B28 (party_meeting_notes), B34 (meeting_plans).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const TABLES = ['party_meeting_notes', 'meeting_plans'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'archived_at')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table): void {
                $this->ts($table, 'archived_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('archived_at');
            });
        }
    }
};
