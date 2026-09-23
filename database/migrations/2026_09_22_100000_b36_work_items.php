<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B36 - Is panosu (D-115, 22 Eylul 2026 kullanici karari; "Konelsis Is Panosu"
 * taslak arayuzu).
 *
 *  - `work_items`: panonun karti. Departman, isin adi, is tarihi (`work_at`
 *    an, `work_on` kurum gunu), proje, ana is (`parent_id`), kategori (kodda
 *    tanimli departman seti), durum (planned | in_progress | waiting | done |
 *    blocked), kritik bayragi, sorumlu personel, termin, kimden bekleniyor
 *    (personel | taraf | serbest metin), bagli kayit (ayri FK kolonlari,
 *    `link_kind` ayirici), kaynak (manual | automatic; otomatik kartin
 *    personel hareketi `source_activity_id`), harcanan saat, not ve sutun
 *    ici sira. Durum gecmisi Personel Hareketleri'ndedir; `*_seconds`
 *    kolonlari o gecmisin surelere izdusumudur (sure raporu ve analiz).
 *  - `work_suggestion_dismissals`: personelin yoksaydigi oneriler (kendi
 *    hareketi). Hareket silinmez; yalniz panoya kart olarak onerilmez.
 *  - `report_items`: + `work_item_id` (dondurulan kart), + `is_late`
 *    (kapatilmis gune sonradan eklenen kart); durum listesine `waiting`.
 *
 * Bagli kayit kolonlari kayit silinince bosalir (nullOnDelete); bu yuzden
 * link_kind tutarliligi CHECK ile degil serviste korunur (MySQL, referans
 * eylemi olan kolonu CHECK icinde kabul etmez).
 *
 * On kosul: B03 (org_units), B08 (personnel_activities), B10A (report_items),
 * B11B (work_requests), B16 (parties, business_cases, proposals,
 * tender_notices), B06 (documents), B17A (project_supply_items), B34 (meeting_plans).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $this->code($table, 'category_code', 40)->nullable();
            $this->status($table)->default('planned');
            $table->boolean('is_critical')->default(false);
            $this->ts($table, 'work_at');
            $table->date('work_on');
            $table->date('due_on')->nullable();
            $table->date('done_on')->nullable();
            $this->status($table, 'waiting_kind')->nullable();
            $table->unsignedBigInteger('waiting_personnel_id')->nullable();
            $table->unsignedBigInteger('waiting_party_id')->nullable();
            $table->string('waiting_text', 200)->nullable();
            $this->ts($table, 'waiting_since')->nullable();
            $this->status($table, 'link_kind')->default('none');
            $table->unsignedBigInteger('linked_business_case_id')->nullable();
            $table->unsignedBigInteger('linked_proposal_id')->nullable();
            $table->unsignedBigInteger('linked_tender_notice_id')->nullable();
            $table->unsignedBigInteger('linked_document_id')->nullable();
            $table->unsignedBigInteger('linked_supply_item_id')->nullable();
            $table->unsignedBigInteger('linked_work_request_id')->nullable();
            $table->unsignedBigInteger('linked_meeting_plan_id')->nullable();
            $this->status($table, 'source')->default('manual');
            $table->unsignedBigInteger('source_activity_id')->nullable();
            $table->decimal('work_hours', 6, 2)->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $this->ts($table, 'status_changed_at');
            $this->ts($table, 'started_at')->nullable();
            $this->ts($table, 'completed_at')->nullable();
            $table->unsignedBigInteger('progress_seconds')->default(0);
            $table->unsignedBigInteger('waiting_seconds')->default(0);
            $table->unsignedBigInteger('blocked_seconds')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('source_activity_id', 'uk_work_items_source_activity');
            $table->index(['personnel_id', 'work_on'], 'ix_work_items_personnel_day');
            $table->index(['status', 'work_on'], 'ix_work_items_status_day');
            $table->index(['project_id', 'status'], 'ix_work_items_project_status');
            $table->index(['org_unit_id', 'work_on'], 'ix_work_items_unit_day');
            $table->index('done_on', 'ix_work_items_done_on');
            $table->index('parent_id', 'ix_work_items_parent');

            $table->foreign('personnel_id', 'fk_work_items_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('org_unit_id', 'fk_work_items_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_id', 'fk_work_items_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('parent_id', 'fk_work_items_parent')
                ->references('id')->on('work_items')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('waiting_personnel_id', 'fk_work_items_waiting_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('waiting_party_id', 'fk_work_items_waiting_party')
                ->references('id')->on('parties')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('linked_business_case_id', 'fk_work_items_business_case')
                ->references('id')->on('business_cases')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('linked_proposal_id', 'fk_work_items_proposal')
                ->references('id')->on('proposals')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('linked_tender_notice_id', 'fk_work_items_tender_notice')
                ->references('id')->on('tender_notices')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('linked_document_id', 'fk_work_items_document')
                ->references('id')->on('documents')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('linked_supply_item_id', 'fk_work_items_supply_item')
                ->references('id')->on('project_supply_items')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('linked_work_request_id', 'fk_work_items_work_request')
                ->references('id')->on('work_requests')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('linked_meeting_plan_id', 'fk_work_items_meeting_plan')
                ->references('id')->on('meeting_plans')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('source_activity_id', 'fk_work_items_source_activity')
                ->references('id')->on('personnel_activities')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('work_items', 'status', ['planned', 'in_progress', 'waiting', 'done', 'blocked']);
        $this->enumCheck('work_items', 'source', ['manual', 'automatic']);
        $this->enumCheck('work_items', 'waiting_kind', ['personnel', 'party', 'text']);
        $this->enumCheck('work_items', 'link_kind', ['none', 'business_case', 'proposal', 'tender_notice', 'document', 'supply_item', 'work_request', 'meeting_plan']);
        $this->check('work_items', 'ck_work_items_hours', '`work_hours` IS NULL OR `work_hours` >= 0');
        $this->personnelForeignKeys('work_items');

        Schema::create('work_suggestion_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('personnel_activity_id');
            $this->auditCreated($table);

            $table->unique(['personnel_id', 'personnel_activity_id'], 'uk_work_suggestion_dismissals');
            $table->foreign('personnel_id', 'fk_work_suggestion_dismissals_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_activity_id', 'fk_work_suggestion_dismissals_activity')
                ->references('id')->on('personnel_activities')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('work_suggestion_dismissals');

        // Donmus rapor kalemi: kaynak kart + "sonradan eklendi"; durum listesine Bekleniyor.
        $this->dropCheck('report_items', 'ck_report_items_status_enum');
        Schema::table('report_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('work_item_id')->nullable()->after('carried_from_item_id');
            $table->boolean('is_late')->default(false)->after('work_item_id');

            $table->index('work_item_id', 'ix_report_items_work_item');
            $table->foreign('work_item_id', 'fk_report_items_work_item')
                ->references('id')->on('work_items')->nullOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('report_items', 'status', ['planned', 'in_progress', 'waiting', 'done', 'blocked']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        $this->dropCheck('report_items', 'ck_report_items_status_enum');
        Schema::table('report_items', function (Blueprint $table): void {
            $table->dropForeign('fk_report_items_work_item');
            $table->dropIndex('ix_report_items_work_item');
            $table->dropColumn(['work_item_id', 'is_late']);
        });
        $this->enumCheck('report_items', 'status', ['planned', 'in_progress', 'done', 'blocked']);

        foreach (['work_suggestion_dismissals' => ['created_by_personnel_id'], 'work_items' => ['created_by_personnel_id', 'updated_by_personnel_id']] as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropForeign($this->fkName($table, $column));
                }
            });
        }

        Schema::dropIfExists('work_suggestion_dismissals');
        Schema::dropIfExists('work_items');
    }

    /** Adlandirilmis CHECK'i kaldirir (yalniz MySQL). */
    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $this->shorten($name)));
    }
};
