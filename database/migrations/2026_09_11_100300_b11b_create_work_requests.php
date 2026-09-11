<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B11B - Talepler (kullanici karari, 11 Eylul 2026, D-84): departman -> personel,
 * departman -> departman, personel -> departman, personel -> personel talep.
 *
 * 07 SS5.1 `tasks` tasariminin talep dilimidir; kanonik `tasks`tan farklar:
 * hedef bir kisi YA DA bir birimdir (`target_kind`), talep eden birim adina
 * konusabilir (`requester_org_unit_id`), ilgili kayit baglari ayri kolonlardir
 * (proje, musteri, urun/bilesen, teklif, is dosyasi, sozlesme, belge — hepsi
 * istege bagli), `source_message_id` sohbetteki mesajdan acilan talebi baglar.
 * `task_assignments`/`task_dependencies` bu dilimde yok; sorumlu tek kisidir
 * (`assignee_personnel_id`).
 *
 * On kosul: B02, B03, B16, B17, B06. `messages` (B12A) uygulanmadiysa
 * `source_message_id` FK'siz kalir; B12A sonrasi ayri ALTER ile eklenebilir.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('work_requests', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'request_no', 32);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $this->status($table, 'priority')->default('normal');
            $this->status($table)->default('open');

            $table->unsignedBigInteger('requester_personnel_id');
            $table->unsignedBigInteger('requester_org_unit_id')->nullable();
            $this->status($table, 'target_kind')->default('personnel');
            $table->unsignedBigInteger('target_personnel_id')->nullable();
            $table->unsignedBigInteger('target_org_unit_id')->nullable();
            $table->unsignedBigInteger('assignee_personnel_id')->nullable();

            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('customer_party_id')->nullable();
            $table->unsignedBigInteger('component_definition_id')->nullable();
            $table->unsignedBigInteger('proposal_id')->nullable();
            $table->unsignedBigInteger('business_case_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('source_message_id')->nullable();

            $table->date('due_on')->nullable();
            $this->ts($table, 'accepted_at')->nullable();
            $this->ts($table, 'completed_at')->nullable();
            $this->ts($table, 'closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_personnel_id')->nullable();
            $table->text('closing_note')->nullable();

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('request_no', 'uk_work_requests_no');
            $table->index(['status', 'target_personnel_id'], 'ix_work_requests_status_target_person');
            $table->index(['status', 'target_org_unit_id'], 'ix_work_requests_status_target_unit');
            $table->index(['status', 'assignee_personnel_id'], 'ix_work_requests_status_assignee');
            $table->index(['requester_personnel_id', 'status'], 'ix_work_requests_requester');
            $table->index('source_message_id', 'ix_work_requests_source_message');

            foreach ([
                'requester_personnel_id' => ['personnel', 'requester'],
                'target_personnel_id' => ['personnel', 'target_person'],
                'assignee_personnel_id' => ['personnel', 'assignee'],
                'closed_by_personnel_id' => ['personnel', 'closer'],
                'requester_org_unit_id' => ['org_units', 'requester_unit'],
                'target_org_unit_id' => ['org_units', 'target_unit'],
                'project_id' => ['projects', 'project'],
                'customer_party_id' => ['parties', 'customer'],
                'component_definition_id' => ['component_definitions', 'component'],
                'proposal_id' => ['proposals', 'proposal'],
                'business_case_id' => ['business_cases', 'business_case'],
                'contract_id' => ['contracts', 'contract'],
                'document_id' => ['documents', 'document'],
            ] as $column => [$references, $suffix]) {
                $table->foreign($column, $this->shorten("fk_work_requests_{$suffix}"))
                    ->references('id')->on($references)->restrictOnDelete()->restrictOnUpdate();
            }
        });

        $this->enumCheck('work_requests', 'priority', ['low', 'normal', 'high', 'critical']);
        $this->enumCheck('work_requests', 'status', ['open', 'in_progress', 'done', 'rejected', 'cancelled']);
        $this->enumCheck('work_requests', 'target_kind', ['personnel', 'org_unit']);
        $this->check(
            'work_requests',
            'ck_work_requests_target',
            "(`target_kind` = 'personnel' AND `target_personnel_id` IS NOT NULL) OR (`target_kind` = 'org_unit' AND `target_org_unit_id` IS NOT NULL)",
        );
        $this->personnelForeignKeys('work_requests');

        if (Schema::hasTable('messages')) {
            Schema::table('work_requests', function (Blueprint $table): void {
                $table->foreign('source_message_id', 'fk_work_requests_source_message')
                    ->references('id')->on('messages')->nullOnDelete()->restrictOnUpdate();
            });
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('work_requests', function (Blueprint $blueprint): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                $blueprint->dropForeign($this->fkName('work_requests', $column));
            }
        });

        Schema::dropIfExists('work_requests');
    }
};
