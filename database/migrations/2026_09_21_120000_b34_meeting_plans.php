<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B34 - Gorusme plani (D-109, 21 Eylul 2026 kullanici karari; Haftalik Ziyaret
 * Plani.xlsx).
 *
 * Hangi personelin ne zaman hangi tarafla gorusecegi / gorustugu tek tabloda
 * durur ve takvimde gosterilir:
 *  - `meeting_plans`: planlanan (planned), gerceklesen (done) ya da
 *    gerceklesmeyen (cancelled) gorusme. Gorusme notlari (B28) buraya yansir:
 *    her not bir "gerceklesti" satiri (`meeting_note_id`), notun tarihli
 *    "sonraki adimi" planli bir satirdir (`follow_up_note_id`). `source`:
 *    manual | meeting_note | follow_up | import.
 *  - `meeting_plan_participants`: gorusmeye katilacak diger personel.
 *  - `meeting_plan_reminders`: gonderilen hatirlatmalar (1 gun once / gunun
 *    sabahi); ayni plan, asama ve tarih icin bir kez gonderilir. Tarih
 *    degisirse yeni tarih icin yeniden gonderilir.
 *
 * On kosul: B16 (parties), B27 (contact_relationships), B28 (party_meeting_notes).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('meeting_plans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('contact_relationship_id')->nullable();
            $table->unsignedBigInteger('personnel_id')->nullable();
            $table->date('planned_on');
            $this->status($table, 'channel')->default('visit');
            $table->string('subject', 200)->nullable();
            $table->text('note')->nullable();
            $this->status($table)->default('planned');
            $this->status($table, 'source')->default('manual');
            $table->unsignedBigInteger('meeting_note_id')->nullable();
            $table->unsignedBigInteger('follow_up_note_id')->nullable();
            $this->ts($table, 'completed_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('meeting_note_id', 'uk_meeting_plans_meeting_note');
            $table->unique('follow_up_note_id', 'uk_meeting_plans_follow_up_note');
            $table->index(['planned_on', 'status'], 'ix_meeting_plans_date_status');
            $table->index(['personnel_id', 'planned_on'], 'ix_meeting_plans_personnel_date');
            $table->index('party_id', 'ix_meeting_plans_party');

            $table->foreign('party_id', 'fk_meeting_plans_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('contact_relationship_id', 'fk_meeting_plans_contact')
                ->references('id')->on('contact_relationships')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_meeting_plans_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('meeting_note_id', 'fk_meeting_plans_meeting_note')
                ->references('id')->on('party_meeting_notes')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('follow_up_note_id', 'fk_meeting_plans_follow_up_note')
                ->references('id')->on('party_meeting_notes')->nullOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('meeting_plans', 'channel', ['visit', 'phone', 'email', 'message', 'other']);
        $this->enumCheck('meeting_plans', 'status', ['planned', 'done', 'cancelled']);
        $this->enumCheck('meeting_plans', 'source', ['manual', 'meeting_note', 'follow_up', 'import']);
        $this->personnelForeignKeys('meeting_plans');

        Schema::create('meeting_plan_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('meeting_plan_id');
            $table->unsignedBigInteger('personnel_id');
            $this->auditCreated($table);

            $table->unique(['meeting_plan_id', 'personnel_id'], 'uk_meeting_plan_participants');
            $table->index('personnel_id', 'ix_meeting_plan_participants_personnel');
            $table->foreign('meeting_plan_id', 'fk_meeting_plan_participants_plan')
                ->references('id')->on('meeting_plans')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_meeting_plan_participants_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('meeting_plan_participants');

        Schema::create('meeting_plan_reminders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('meeting_plan_id');
            $this->status($table, 'stage');
            $table->date('due_on');
            $table->unsignedSmallInteger('recipient_count')->default(0);
            $this->ts($table, 'sent_at');

            $table->unique(['meeting_plan_id', 'stage', 'due_on'], 'uk_meeting_plan_reminders');
            $table->foreign('meeting_plan_id', 'fk_meeting_plan_reminders_plan')
                ->references('id')->on('meeting_plans')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('meeting_plan_reminders', 'stage', ['day_before', 'same_day']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach (['meeting_plan_participants' => ['created_by_personnel_id'], 'meeting_plans' => ['created_by_personnel_id', 'updated_by_personnel_id']] as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropForeign($this->fkName($table, $column));
                }
            });
        }

        Schema::dropIfExists('meeting_plan_reminders');
        Schema::dropIfExists('meeting_plan_participants');
        Schema::dropIfExists('meeting_plans');
    }
};
