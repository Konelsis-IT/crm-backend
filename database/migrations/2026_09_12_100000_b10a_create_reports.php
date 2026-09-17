<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B10A - Raporlar, kod tanimli taslaklar (kullanici karari, 12 Eylul 2026, D-86).
 *
 * 07 SS1-3'teki veritabani tabanli sablon modelinin (report_templates,
 * report_sections, report_questions, report_answers...) yerine taslaklar
 * kodda tanimlanir (App\Reports\Templates). Bu batch yalniz uc tablo kurar:
 *
 *  - `reports`        : rapor kaydi; taslak kodu, tur, yazar, konu baglari
 *                       (personel / proje / urun-bilesen / teklif / is dosyasi,
 *                       ayri kolonlar), donem, durum, inceleme ve taslaga ozel
 *                       cevaplar (`payload` JSON; sekli taslak sinifi belirler).
 *  - `report_items`   : pano tipli raporlarin (gunluk / haftalik / aylik) is
 *                       kalemleri; durum kolonlariyla "is panosu" gorunumu.
 *  - `report_metrics` : taslagin KPI olarak isaretledigi sayisal cevaplarin
 *                       gonderimde yazilan projeksiyonu (07 SS3.8'in sade hali).
 *
 * On kosul: B00 (personnel), B03 (org_units), B16 (proposals, business_cases),
 * B17 (projects, component_definitions).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'report_no', 32);
            $this->code($table, 'template_code', 64);
            $this->status($table, 'kind');
            $table->string('title', 200);
            $this->status($table)->default('draft');
            $table->boolean('is_confidential')->default(false);

            $table->unsignedBigInteger('author_personnel_id');
            $table->unsignedBigInteger('author_org_unit_id')->nullable();

            $this->status($table, 'subject_kind')->default('none');
            $table->unsignedBigInteger('subject_personnel_id')->nullable();
            $table->unsignedBigInteger('subject_project_id')->nullable();
            $table->unsignedBigInteger('subject_component_definition_id')->nullable();
            $table->unsignedBigInteger('subject_proposal_id')->nullable();
            $table->unsignedBigInteger('subject_business_case_id')->nullable();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();

            $this->ts($table, 'submitted_at')->nullable();
            $table->unsignedBigInteger('reviewer_personnel_id')->nullable();
            $this->ts($table, 'reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->unsignedSmallInteger('revision_count')->default(0);

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('report_no', 'uk_reports_no');
            $table->index(['author_personnel_id', 'status'], 'ix_reports_author_status');
            $table->index(['status', 'reviewer_personnel_id'], 'ix_reports_status_reviewer');
            $table->index(['template_code', 'author_personnel_id', 'period_start'], 'ix_reports_template_author_period');
            $table->index(['kind', 'status'], 'ix_reports_kind_status');

            foreach ([
                'author_personnel_id' => ['personnel', 'author'],
                'reviewer_personnel_id' => ['personnel', 'reviewer'],
                'subject_personnel_id' => ['personnel', 'subject_person'],
                'author_org_unit_id' => ['org_units', 'author_unit'],
                'subject_project_id' => ['projects', 'subject_project'],
                'subject_component_definition_id' => ['component_definitions', 'subject_component'],
                'subject_proposal_id' => ['proposals', 'subject_proposal'],
                'subject_business_case_id' => ['business_cases', 'subject_business_case'],
            ] as $column => [$references, $suffix]) {
                $table->foreign($column, $this->shorten("fk_reports_{$suffix}"))
                    ->references('id')->on($references)->restrictOnDelete()->restrictOnUpdate();
            }
        });

        $this->enumCheck('reports', 'status', ['draft', 'submitted', 'approved', 'revision_required', 'rejected']);
        $this->enumCheck('reports', 'kind', ['daily', 'weekly', 'monthly', 'project', 'product', 'proposal', 'business_case', 'personnel', 'system']);
        $this->enumCheck('reports', 'subject_kind', ['none', 'personnel', 'project', 'component', 'proposal', 'business_case']);
        $this->check(
            'reports',
            'ck_reports_period_range',
            '`period_start` IS NULL OR `period_end` IS NULL OR `period_end` >= `period_start`',
        );
        $this->check(
            'reports',
            'ck_reports_subject',
            "(`subject_kind` = 'none')"
            ." OR (`subject_kind` = 'personnel' AND `subject_personnel_id` IS NOT NULL)"
            ." OR (`subject_kind` = 'project' AND `subject_project_id` IS NOT NULL)"
            ." OR (`subject_kind` = 'component' AND `subject_component_definition_id` IS NOT NULL)"
            ." OR (`subject_kind` = 'proposal' AND `subject_proposal_id` IS NOT NULL)"
            ." OR (`subject_kind` = 'business_case' AND `subject_business_case_id` IS NOT NULL)",
        );
        $this->personnelForeignKeys('reports');

        Schema::create('report_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('title', 200);
            $table->string('description', 1000)->nullable();
            $this->status($table)->default('planned');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->decimal('work_hours', 6, 2)->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedBigInteger('carried_from_item_id')->nullable();

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['report_id', 'sort_order'], 'ix_report_items_report_order');
            $table->index(['report_id', 'status'], 'ix_report_items_report_status');

            $table->foreign('report_id', 'fk_report_items_report')
                ->references('id')->on('reports')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('project_id', 'fk_report_items_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('carried_from_item_id', 'fk_report_items_carried_from')
                ->references('id')->on('report_items')->nullOnDelete()->restrictOnUpdate();
        });

        $this->enumCheck('report_items', 'status', ['planned', 'in_progress', 'done', 'blocked']);
        $this->check('report_items', 'ck_report_items_hours', '`work_hours` IS NULL OR `work_hours` >= 0');
        $this->personnelForeignKeys('report_items');

        Schema::create('report_metrics', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $this->code($table, 'metric_code', 64);
            $table->decimal('metric_value', 24, 8);
            $table->string('unit', 16)->nullable();
            $this->ts($table, 'projected_at');

            $this->auditCreated($table);

            $table->unique(['report_id', 'metric_code'], 'uk_report_metrics_report_code');
            $table->index(['metric_code', 'projected_at'], 'ix_report_metrics_code_time');

            $table->foreign('report_id', 'fk_report_metrics_report')
                ->references('id')->on('reports')->cascadeOnDelete()->restrictOnUpdate();
        });

        $this->personnelForeignKeys('report_metrics');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach (['report_metrics', 'report_items', 'reports'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropForeign($this->fkName($table, $column));
                    }
                }
            });

            Schema::dropIfExists($table);
        }
    }
};
