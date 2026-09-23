<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B37 - Is kartinda "Talep eden" (D-118, 23 Eylul 2026 kullanici karari:
 * "Ayrica Yeni kalem kismina talep eden, kismi ekleyelim").
 *
 * Isi kimin istedigi, "kimden bekleniyor" alaniyla ayni bicimde tutulur:
 * ic personel, taraf (musteri / tedarikci / kurum) ya da serbest metin.
 * Bos birakilabilir: kisinin kendi isinde talep eden yoktur.
 *
 * On kosul: B36 (work_items).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table): void {
            $this->status($table, 'requester_kind')->nullable()->after('waiting_since');
            $table->unsignedBigInteger('requester_personnel_id')->nullable()->after('requester_kind');
            $table->unsignedBigInteger('requester_party_id')->nullable()->after('requester_personnel_id');
            $table->string('requester_text', 200)->nullable()->after('requester_party_id');

            $table->index('requester_personnel_id', 'ix_work_items_requester_personnel');
            $table->index('requester_party_id', 'ix_work_items_requester_party');

            $table->foreign('requester_personnel_id', 'fk_work_items_requester_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('requester_party_id', 'fk_work_items_requester_party')
                ->references('id')->on('parties')->nullOnDelete()->restrictOnUpdate();
        });

        $this->enumCheck('work_items', 'requester_kind', ['personnel', 'party', 'text']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        // MySQL, CHECK'in kullandigi kolonu dusurmez; once kisit kaldirilir.
        if ($this->isMySql()) {
            DB::statement(sprintf('ALTER TABLE `work_items` DROP CHECK `%s`', $this->shorten('ck_work_items_requester_kind_enum')));
        }

        Schema::table('work_items', function (Blueprint $table): void {
            $table->dropForeign('fk_work_items_requester_personnel');
            $table->dropForeign('fk_work_items_requester_party');
            $table->dropIndex('ix_work_items_requester_personnel');
            $table->dropIndex('ix_work_items_requester_party');
            $table->dropColumn(['requester_kind', 'requester_personnel_id', 'requester_party_id', 'requester_text']);
        });
    }
};
