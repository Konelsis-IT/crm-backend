<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B57 - Is sisteminin yukseltilmesi (D-219, 16 Ekim 2026 kullanici talimati:
 * "bir iş birden fazla kişiye atanabilir ... iş sistemini bu seviyeye
 * yükselt", "Tüm ekip ... personelden bağımsız olarak iş eklenebilme", "Bir
 * iş için birden fazla notta eklenebilir", "iş model yapımıza çoklu belge
 * eklenebilme sistemi eklenecek", "kritikliği 4 seviyeye çıkar", "her
 * personel için tekrarlı iş sistemi eklemeliyiz. Görev ve sorumluluklar gibi
 * düşünebiliriz").
 *
 *  - `recurring_works`: gorev ve sorumluluk (tekrarli is) tanimi: sorumlu,
 *    siklik (gunluk / haftalik / aylik), tur (rapor / mail kutusu / is),
 *    neye bakilacagi, departman, oncelik, aktif, arsiv.
 *  - `work_item_assignees`: isin diger sorumlulari (ana sorumlu
 *    `work_items.personnel_id`).
 *  - `work_item_notes`: isin tarihli notlari (kaynak: mail, WhatsApp...).
 *  - `work_item_files`: isin belgeleri (file_objects).
 *  - `work_items`: + `priority` (low | normal | high | critical; eski kritik
 *    bayragi critical olarak doldurulur, `is_critical` onunla esit tutulur),
 *    + `for_everyone` (tum ekibe verilen is), + `discipline` (is grubu:
 *    insaat, mekanik, elektrik, yardimci sistemler, SCADA, ENH, kabul),
 *    + `linked_purchase_need_id` (bagli kayit: satin alinacak kalem),
 *    + `recurring_work_id` / `period_key` (tekrarli isin donemi),
 *    + `legacy_no` (aktarilan listedeki eski is numarasi).
 *
 * On kosul: B36 (work_items), B06 (file_objects), B52 (purchase_needs).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurring_works')) {
            Schema::create('recurring_works', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $this->status($table, 'kind')->default('task');
                $this->status($table, 'frequency')->default('daily');
                $table->unsignedTinyInteger('weekday')->nullable();
                $table->unsignedTinyInteger('month_day')->nullable();
                $table->unsignedBigInteger('personnel_id');
                $table->unsignedBigInteger('org_unit_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $this->code($table, 'category_code', 40)->nullable();
                $this->status($table, 'priority')->default('normal');
                $table->boolean('is_active')->default(true);
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $this->ts($table, 'archived_at')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->index(['personnel_id', 'is_active'], 'ix_recurring_works_personnel_active');
                $table->foreign('personnel_id', 'fk_recurring_works_personnel')
                    ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('org_unit_id', 'fk_recurring_works_org_unit')
                    ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('project_id', 'fk_recurring_works_project')
                    ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->enumCheck('recurring_works', 'kind', ['report', 'mailbox', 'task']);
            $this->enumCheck('recurring_works', 'frequency', ['daily', 'weekly', 'monthly']);
            $this->enumCheck('recurring_works', 'priority', ['low', 'normal', 'high', 'critical']);
            $this->check('recurring_works', 'ck_recurring_works_weekday', '`weekday` IS NULL OR (`weekday` BETWEEN 1 AND 7)');
            $this->check('recurring_works', 'ck_recurring_works_month_day', '`month_day` IS NULL OR (`month_day` BETWEEN 1 AND 31)');
            $this->personnelForeignKeys('recurring_works');
        }

        if (! Schema::hasTable('work_item_assignees')) {
            Schema::create('work_item_assignees', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('work_item_id');
                $table->unsignedBigInteger('personnel_id');
                $this->auditCreated($table);

                $table->unique(['work_item_id', 'personnel_id'], 'uk_work_item_assignees');
                $table->index('personnel_id', 'ix_work_item_assignees_personnel');
                $table->foreign('work_item_id', 'fk_work_item_assignees_item')
                    ->references('id')->on('work_items')->cascadeOnDelete()->restrictOnUpdate();
                $table->foreign('personnel_id', 'fk_work_item_assignees_personnel')
                    ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys('work_item_assignees');
        }

        if (! Schema::hasTable('work_item_notes')) {
            Schema::create('work_item_notes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('work_item_id');
                $table->text('body');
                $table->string('source', 40)->nullable();
                $this->ts($table, 'noted_at');
                $this->auditCreated($table);

                $table->index(['work_item_id', 'noted_at'], 'ix_work_item_notes_item');
                $table->foreign('work_item_id', 'fk_work_item_notes_item')
                    ->references('id')->on('work_items')->cascadeOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys('work_item_notes');
        }

        if (! Schema::hasTable('work_item_files')) {
            Schema::create('work_item_files', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('work_item_id');
                $table->unsignedBigInteger('file_object_id');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $this->auditCreated($table);

                $table->index('work_item_id', 'ix_work_item_files_item');
                $table->foreign('work_item_id', 'fk_work_item_files_item')
                    ->references('id')->on('work_items')->cascadeOnDelete()->restrictOnUpdate();
                $table->foreign('file_object_id', 'fk_work_item_files_file')
                    ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys('work_item_files');
        }

        if (! Schema::hasColumn('work_items', 'legacy_no')) {
            Schema::table('work_items', function (Blueprint $table): void {
                $this->status($table, 'priority')->default('normal')->after('is_critical');
                $table->boolean('for_everyone')->default(false)->after('personnel_id');
                $this->status($table, 'discipline')->nullable()->after('category_code');
                $table->unsignedBigInteger('linked_purchase_need_id')->nullable()->after('linked_meeting_plan_id');
                $table->unsignedBigInteger('recurring_work_id')->nullable();
                $table->string('period_key', 16)->nullable();
                $table->string('legacy_no', 20)->nullable();

                $table->index(['for_everyone', 'status'], 'ix_work_items_for_everyone');
                $table->index(['priority', 'status'], 'ix_work_items_priority');
                $table->index('legacy_no', 'ix_work_items_legacy_no');
                $table->unique(['recurring_work_id', 'period_key'], 'uk_work_items_recurring_period');
                $table->foreign('linked_purchase_need_id', 'fk_work_items_purchase_need')
                    ->references('id')->on('purchase_needs')->nullOnDelete()->restrictOnUpdate();
                $table->foreign('recurring_work_id', 'fk_work_items_recurring_work')
                    ->references('id')->on('recurring_works')->restrictOnDelete()->restrictOnUpdate();
            });

            // Eski kritik bayragi yeni oncelikte "Kritik".
            DB::table('work_items')->where('is_critical', true)->update(['priority' => 'critical']);

            $this->enumCheck('work_items', 'priority', ['low', 'normal', 'high', 'critical']);
            $this->enumCheck('work_items', 'discipline', ['construction', 'mechanical', 'electrical', 'auxiliary', 'scada', 'enh', 'acceptance']);
            $this->dropCheck('work_items', 'ck_work_items_link_kind_enum');
            $this->enumCheck('work_items', 'link_kind', ['none', 'business_case', 'proposal', 'tender_notice', 'document', 'supply_item', 'work_request', 'meeting_plan', 'purchase_need']);
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (Schema::hasColumn('work_items', 'legacy_no')) {
            $this->dropCheck('work_items', 'ck_work_items_link_kind_enum');
            DB::table('work_items')->where('link_kind', 'purchase_need')->update(['link_kind' => 'none']);
            $this->enumCheck('work_items', 'link_kind', ['none', 'business_case', 'proposal', 'tender_notice', 'document', 'supply_item', 'work_request', 'meeting_plan']);
            $this->dropCheck('work_items', 'ck_work_items_priority_enum');
            $this->dropCheck('work_items', 'ck_work_items_discipline_enum');

            Schema::table('work_items', function (Blueprint $table): void {
                $table->dropForeign('fk_work_items_purchase_need');
                $table->dropForeign('fk_work_items_recurring_work');
                $table->dropUnique('uk_work_items_recurring_period');
                $table->dropIndex('ix_work_items_for_everyone');
                $table->dropIndex('ix_work_items_priority');
                $table->dropIndex('ix_work_items_legacy_no');
                $table->dropColumn(['priority', 'for_everyone', 'discipline', 'linked_purchase_need_id', 'recurring_work_id', 'period_key', 'legacy_no']);
            });
        }

        Schema::dropIfExists('work_item_files');
        Schema::dropIfExists('work_item_notes');
        Schema::dropIfExists('work_item_assignees');
        Schema::dropIfExists('recurring_works');
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $this->shorten($name)));
    }
};
