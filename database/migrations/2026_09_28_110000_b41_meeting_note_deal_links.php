<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B41 - Gorusme notlarinin potansiyel is ve teklife baglanmasi (D-137,
 * 28 Eylul 2026 kullanici karari: "Gorusme notlari ... teklif ile baglamamiz
 * lazim teklif'in de Potansiyel islerinde gorusme notlari ile relation kurmasi
 * gerek ... relation'da gosterilmesi gerekiyor.").
 *
 * - `party_meeting_notes.business_case_id`: notun ait oldugu potansiyel is
 *   (bos = yalniz tarafla ilgili genel gorusme). Not tarafa bagli kalir.
 * - `party_meeting_note_proposals`: notun konustugu teklifler (ayni potansiyel
 *   isin bir ya da birden fazla teklifi; servis ayni potansiyel is kuralini
 *   uygular). Not silinirse baglantilari da silinir; teklifi olan not varken
 *   teklif silinemez.
 *
 * On kosul: B16 (business_cases, proposals), B28 (party_meeting_notes).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('party_meeting_notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('business_case_id')->nullable()->after('party_id');

            $table->index(['business_case_id', 'noted_on'], 'ix_party_meeting_notes_case_date');
            $table->foreign('business_case_id', 'fk_party_meeting_notes_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('party_meeting_note_proposals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_meeting_note_id');
            $table->unsignedBigInteger('proposal_id');
            $this->auditCreated($table);

            $table->unique(['party_meeting_note_id', 'proposal_id'], 'uk_party_meeting_note_proposals');
            $table->index('proposal_id', 'ix_party_meeting_note_proposals_proposal');
            $table->foreign('party_meeting_note_id', 'fk_party_meeting_note_proposals_note')
                ->references('id')->on('party_meeting_notes')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('proposal_id', 'fk_party_meeting_note_proposals_proposal')
                ->references('id')->on('proposals')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('party_meeting_note_proposals');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('party_meeting_note_proposals', function (Blueprint $table): void {
            $table->dropForeign($this->fkName('party_meeting_note_proposals', 'created_by_personnel_id'));
            $table->dropForeign('fk_party_meeting_note_proposals_note');
            $table->dropForeign('fk_party_meeting_note_proposals_proposal');
        });

        Schema::dropIfExists('party_meeting_note_proposals');

        Schema::table('party_meeting_notes', function (Blueprint $table): void {
            $table->dropForeign('fk_party_meeting_notes_business_case');
            $table->dropIndex('ix_party_meeting_notes_case_date');
            $table->dropColumn('business_case_id');
        });
    }
};
