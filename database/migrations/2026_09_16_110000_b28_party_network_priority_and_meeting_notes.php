<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B28 - Taraf network notu, ziyaret onceligi ve gorusme notlari (D-98,
 * 16 Eylul 2026 kullanici talimati).
 *
 * Network: bir taraf (firma ya da kisi) nereden taniniyor, hangi cevreden
 * ulasildi? `parties.network_note` ve `contact_relationships.network_note`
 * bu bilgiyi serbest metin olarak tasir; ayri bir katalog acilmaz. Firmanin
 * kendi notu ile firmadaki kisinin notu ayri ayri yazilabilir.
 *
 * Ziyaret onceligi: `parties.visit_priority` tarafin nasil takip edilecegini
 * soyler: acil ziyaret, rutin gorusme ya da telefon. Deger sabit listeden
 * gelir (enumCheck) ve listeyi oncelige gore suzmek/siralamak icin
 * `ix_parties_visit_priority` indeksi vardir. Bos ise oncelik verilmemistir.
 *
 * Gorusme notlari: `party_meeting_notes` bir tarafla yapilan her gorusmenin
 * (ziyaret, telefon, e-posta, mesaj) tarihini, kanalini, gorusen Konelsis
 * personelini, karsidaki kisiyi ve notu tutar; bir sonraki adim ve tarihi
 * ayni satirda yazilir. Kisi baglantisi NULL edilebilir: kisi silinirse not
 * kalir, baglanti bosalir. Notu olan taraf ve personel silinemez (RESTRICT).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->string('network_note', 255)->nullable()->after('status');
            $this->status($table, 'visit_priority')->nullable()->after('network_note');

            $table->index('visit_priority', 'ix_parties_visit_priority');
        });
        $this->enumCheck('parties', 'visit_priority', ['urgent_visit', 'routine_meeting', 'phone']);

        Schema::table('contact_relationships', function (Blueprint $table): void {
            $table->string('network_note', 255)->nullable()->after('department_note');
        });

        Schema::create('party_meeting_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('contact_relationship_id')->nullable();
            $table->unsignedBigInteger('personnel_id')->nullable();
            $table->date('noted_on');
            $this->status($table, 'channel')->default('phone');
            $table->string('subject', 200)->nullable();
            $table->text('note');
            $table->date('next_action_on')->nullable();
            $table->string('next_action', 255)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['party_id', 'noted_on'], 'ix_party_meeting_notes_party_date');
            $table->foreign('party_id', 'fk_party_meeting_notes_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('contact_relationship_id', 'fk_party_meeting_notes_contact')
                ->references('id')->on('contact_relationships')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_party_meeting_notes_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('party_meeting_notes', 'channel', ['visit', 'phone', 'email', 'message', 'other']);
        $this->personnelForeignKeys('party_meeting_notes');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('party_meeting_notes', function (Blueprint $blueprint): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id', 'archived_by_personnel_id'] as $column) {
                if (Schema::hasColumn('party_meeting_notes', $column)) {
                    $blueprint->dropForeign($this->fkName('party_meeting_notes', $column));
                }
            }
        });

        Schema::dropIfExists('party_meeting_notes');

        Schema::table('contact_relationships', function (Blueprint $table): void {
            $table->dropColumn('network_note');
        });

        // MySQL, CHECK'in kullandigi kolonu dusurmeye izin vermez; once kisit kalkar.
        $this->dropCheck('parties', 'ck_parties_visit_priority_enum');

        Schema::table('parties', function (Blueprint $table): void {
            $table->dropIndex('ix_parties_visit_priority');
            $table->dropColumn(['visit_priority', 'network_note']);
        });
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $name));
    }
};
