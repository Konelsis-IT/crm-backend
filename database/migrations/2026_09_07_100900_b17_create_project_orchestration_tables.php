<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B17 - Proje/Operasyon orkestrasyonu (docs/planning/11 SS1-3, M12; karar D-67).
 *
 * Proje kaydi yalniz Operasyona devir kabul transaction'inda dogar
 * (`projects.accepted_handoff_version_id` NOT NULL UNIQUE; 14 SS2.24).
 * Workstream, stage-gate, departman devri, WBS/CBS, baseline, milestone,
 * ilerleme, issue/risk/gecikme/degisiklik ve ticari maruziyet tablolari
 * burada kurulur.
 *
 * Ertelenmis FK deseni (B06/B16 ile ayni): `stage_nodes.approval_policy_id`,
 * `*.approval_request_id` (B07), `stage_waivers.remediation_task_id` (B11),
 * `progress_snapshots.report_submission_version_id` (B10),
 * `project_decisions.source_message_id` (B12) kolonlari simdilik yoktur.
 * `project_tasks` tablosu `tasks` (B11) uzantisi oldugu icin (D-21) o batch
 * ile birlikte kurulur; `budget_baselines` B20'nindir.
 *
 * "Ayni proje" garantisi gereken her cocuk FK, parent'taki `(project_id, id)`
 * composite unique uzerinden composite FK ile baglanir (13 SS2). Karsilikli
 * FK'lar (sablon current surum, proje primary focus, departman devri kabul
 * surumu) tablolar olustuktan sonra ALTER ile eklenir.
 *
 * DMS'te ertelenmis olan `documents.project_id` ve `transmittals.project_id`
 * bu batch'in sonunda eklenir.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'component_definitions', 'operation_group_definitions', 'stage_templates', 'stage_template_versions',
        'stage_nodes', 'stage_dependencies', 'stage_requirement_definitions', 'projects', 'project_components',
        'project_workstreams', 'workstream_dependencies', 'project_focus_histories', 'wbs_nodes', 'cbs_nodes',
        'wbs_cbs_mappings', 'work_packages', 'work_package_dependencies', 'project_stage_instances',
        'project_stage_requirements', 'stage_evidence', 'stage_reviews', 'stage_waivers', 'department_handoffs',
        'department_handoff_versions', 'department_handoff_items', 'department_handoff_reviews',
        'schedule_baselines', 'milestones', 'progress_snapshots', 'project_issues', 'project_risks',
        'delay_events', 'recovery_actions', 'project_changes', 'commercial_clarifications',
        'commercial_exposures', 'project_decisions',
    ];

    /** @var list<string> */
    private const COST_CATEGORIES = [
        'material', 'labor', 'subcontract', 'logistics', 'engineering', 'commissioning', 'overhead', 'contingency', 'other',
    ];

    public function up(): void
    {
        $this->createCatalogAndTemplateTables();
        $this->createProjectRootTables();
        $this->createStructureTables();
        $this->createStageInstanceTables();
        $this->createDepartmentHandoffTables();
        $this->createControlTables();
        $this->addCrossReferences();

        foreach (self::AUDITED_TABLES as $table) {
            $this->personnelForeignKeys($table);
        }
    }

    private function createCatalogAndTemplateTables(): void
    {
        Schema::create('component_definitions', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name_tr');
            $table->string('name_en');
            $this->code($table, 'discipline', 32);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_component_definitions_code');
        });
        $this->enumCheck('component_definitions', 'status', ['active', 'inactive']);

        Schema::create('operation_group_definitions', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedTinyInteger('default_sort_order')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_operation_group_definitions_code');
        });
        $this->enumCheck('operation_group_definitions', 'status', ['active', 'inactive']);

        Schema::create('stage_templates', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code');
            $table->string('name_tr');
            $table->string('name_en');
            $this->code($table, 'project_type', 32)->default('generic');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $this->status($table)->default('draft');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_stage_templates_code');
        });
        $this->enumCheck('stage_templates', 'project_type', ['generic', 'ges', 'hes', 'res', 'bess', 'ems', 'enh', 'mixed']);
        $this->enumCheck('stage_templates', 'status', ['draft', 'active', 'retired']);

        Schema::create('stage_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stage_template_id');
            $table->unsignedInteger('version_no');
            $this->status($table)->default('draft');
            $table->text('change_summary')->nullable();
            $this->ascii($table, 'definition_hash', 64)->nullable();
            $table->unsignedBigInteger('published_by_personnel_id')->nullable();
            $this->ts($table, 'published_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['stage_template_id', 'version_no'], 'uk_stage_template_versions_template_version_no');
            $table->unique(['stage_template_id', 'id'], 'uk_stage_template_versions_template_id');
            $table->foreign('stage_template_id', 'fk_stage_template_versions_template')
                ->references('id')->on('stage_templates')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('published_by_personnel_id', 'fk_stage_template_versions_publisher')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('stage_template_versions', 'status', ['draft', 'published', 'superseded']);

        Schema::create('stage_nodes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stage_template_version_id');
            $this->code($table, 'stage_code', 32);
            $table->string('name_tr');
            $table->string('name_en');
            $table->smallInteger('sequence_no')->default(0);
            $table->boolean('is_hard_gate')->default(true);
            $table->unsignedBigInteger('owner_group_definition_id')->nullable();
            $table->text('description')->nullable();
            $this->auditCreated($table);

            $table->unique(['stage_template_version_id', 'stage_code'], 'uk_stage_nodes_version_code');
            $table->foreign('stage_template_version_id', 'fk_stage_nodes_template_version')
                ->references('id')->on('stage_template_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_group_definition_id', 'fk_stage_nodes_owner_group')
                ->references('id')->on('operation_group_definitions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('stage_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('predecessor_node_id');
            $table->unsignedBigInteger('successor_node_id');
            $table->boolean('is_hard')->default(true);
            $this->auditCreated($table);

            $table->unique(['predecessor_node_id', 'successor_node_id'], 'uk_stage_dependencies_pair');
            $table->foreign('predecessor_node_id', 'fk_stage_dependencies_predecessor')
                ->references('id')->on('stage_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('successor_node_id', 'fk_stage_dependencies_successor')
                ->references('id')->on('stage_nodes')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('stage_dependencies', 'ck_stage_dependencies_no_self_link', '`predecessor_node_id` <> `successor_node_id`');

        Schema::create('stage_requirement_definitions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stage_node_id');
            $this->code($table, 'requirement_code', 32);
            $table->string('name_tr');
            $table->string('name_en');
            $this->status($table, 'evidence_type');
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedBigInteger('min_document_type_id')->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);

            $table->unique(['stage_node_id', 'requirement_code'], 'uk_srd_node_code');
            $table->foreign('stage_node_id', 'fk_srd_stage_node')
                ->references('id')->on('stage_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('min_document_type_id', 'fk_srd_min_document_type')
                ->references('id')->on('document_types')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('stage_requirement_definitions', 'evidence_type', [
            'document', 'approval', 'checklist', 'measurement', 'external_check', 'handoff',
        ]);
    }

    private function createProjectRootTables(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $table->unsignedBigInteger('project_business_code_id');
            $table->unsignedBigInteger('accepted_handoff_version_id');
            $table->string('name');
            $table->unsignedBigInteger('customer_party_id');
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('project_manager_employee_id');
            $this->asciiChar($table, 'country_code', 2);
            $this->ascii($table, 'timezone', 64)->default('Europe/Istanbul');
            $table->string('site_location', 100)->nullable();
            $this->asciiChar($table, 'currency_code', 3);
            $table->decimal('contract_value_snapshot', 20, 4)->nullable();
            $table->unsignedBigInteger('stage_template_version_id');
            $this->status($table, 'criticality_profile')->default('standard');
            $this->status($table)->default('opening');
            $this->code($table, 'current_macro_gate_code', 32)->nullable();
            $table->unsignedBigInteger('primary_focus_workstream_id')->nullable();
            $table->unsignedBigInteger('cover_file_object_id')->nullable();
            $table->unsignedBigInteger('classification_id');
            $table->date('planned_start_on')->nullable();
            $table->date('planned_finish_on')->nullable();
            $table->date('actual_start_on')->nullable();
            $table->date('actual_finish_on')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('business_case_id', 'uk_projects_business_case');
            $table->unique('project_business_code_id', 'uk_projects_business_code');
            $table->unique('accepted_handoff_version_id', 'uk_projects_accepted_handoff_version');
            $table->index('status', 'ix_projects_status');
            $table->foreign('business_case_id', 'fk_projects_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_business_code_id', 'fk_projects_business_code')
                ->references('id')->on('business_codes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('accepted_handoff_version_id', 'fk_projects_accepted_handoff_version')
                ->references('id')->on('operation_handoff_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('customer_party_id', 'fk_projects_customer_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('legal_entity_id', 'fk_projects_legal_entity')
                ->references('id')->on('legal_entities')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_manager_employee_id', 'fk_projects_project_manager')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('country_code', 'fk_projects_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_projects_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('stage_template_version_id', 'fk_projects_stage_template_version')
                ->references('id')->on('stage_template_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('cover_file_object_id', 'fk_projects_cover_file')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('classification_id', 'fk_projects_classification')
                ->references('id')->on('security_classifications')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('projects', 'criticality_profile', ['standard', 'critical', 'strategic']);
        $this->enumCheck('projects', 'status', ['opening', 'active', 'acceptance', 'warranty', 'closed', 'suspended', 'cancelled']);
        $this->check('projects', 'ck_projects_date_order', '`planned_finish_on` IS NULL OR `planned_start_on` IS NULL OR `planned_finish_on` >= `planned_start_on`');

        Schema::create('project_components', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('component_definition_id');
            $this->status($table, 'scope_state')->default('planned');
            $table->decimal('capacity_value', 18, 6)->nullable();
            $table->unsignedBigInteger('capacity_uom_id')->nullable();
            $table->text('note')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'component_definition_id'], 'uk_project_components_project_component');
            $table->foreign('project_id', 'fk_project_components_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('component_definition_id', 'fk_project_components_definition')
                ->references('id')->on('component_definitions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('capacity_uom_id', 'fk_project_components_capacity_uom')
                ->references('id')->on('units_of_measure')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_components', 'scope_state', ['planned', 'in_scope', 'out_of_scope', 'completed']);

        Schema::create('project_workstreams', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('group_definition_id');
            $table->unsignedBigInteger('owner_personnel_id');
            $this->status($table)->default('not_ready');
            $table->decimal('progress_pct', 7, 4)->default(0);
            $table->date('planned_start_on')->nullable();
            $table->date('planned_finish_on')->nullable();
            $table->date('actual_start_on')->nullable();
            $table->date('actual_finish_on')->nullable();
            $this->ts($table, 'blocked_at')->nullable();
            $table->text('block_reason')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'id'], 'uk_project_workstreams_project_id');
            $table->unique(['project_id', 'group_definition_id'], 'uk_project_workstreams_project_group');
            $table->foreign('project_id', 'fk_project_workstreams_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('group_definition_id', 'fk_project_workstreams_group')
                ->references('id')->on('operation_group_definitions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_project_workstreams_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_workstreams', 'status', [
            'not_ready', 'ready', 'active', 'review', 'completed', 'blocked', 'waived', 'cancelled',
        ]);
        $this->check('project_workstreams', 'ck_project_workstreams_pct_range', '`progress_pct` >= 0 AND `progress_pct` <= 100');
        $this->check('project_workstreams', 'ck_project_workstreams_block_reason', "`status` <> 'blocked' OR `block_reason` IS NOT NULL");

        Schema::create('workstream_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('predecessor_workstream_id');
            $table->unsignedBigInteger('successor_workstream_id');
            $this->status($table, 'dependency_type')->default('FS');
            $table->smallInteger('lag_days')->default(0);
            $table->boolean('is_hard')->default(true);
            $this->status($table)->default('active');
            $table->unsignedBigInteger('waived_by_personnel_id')->nullable();
            $table->text('waiver_reason')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['predecessor_workstream_id', 'successor_workstream_id'], 'uk_workstream_dependencies_pair');
            $table->foreign('predecessor_workstream_id', 'fk_workstream_dependencies_predecessor')
                ->references('id')->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('successor_workstream_id', 'fk_workstream_dependencies_successor')
                ->references('id')->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('waived_by_personnel_id', 'fk_workstream_dependencies_waiver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('workstream_dependencies', 'dependency_type', ['FS', 'SS', 'FF', 'SF']);
        $this->enumCheck('workstream_dependencies', 'status', ['active', 'waived']);
        $this->check('workstream_dependencies', 'ck_workstream_dependencies_no_self_link', '`predecessor_workstream_id` <> `successor_workstream_id`');

        Schema::create('project_focus_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('workstream_id');
            $this->status($table, 'direction')->default('initial');
            $this->ts($table, 'started_at');
            $this->ts($table, 'ended_at')->nullable();
            $table->unsignedBigInteger('changed_by_personnel_id');
            $table->text('reason')->nullable();

            if ($this->isMySql()) {
                $table->unsignedBigInteger('open_guard')
                    ->storedAs('CASE WHEN `ended_at` IS NULL THEN `project_id` END')
                    ->nullable();
            } else {
                $table->unsignedBigInteger('open_guard')->nullable();
            }

            $this->auditCreated($table);

            $table->unique('open_guard', 'uk_project_focus_histories_open_guard');
            $table->index(['project_id', 'started_at'], 'ix_project_focus_histories_project_started');
            // restrictOnDelete: project_id, open_guard'in taban kolonu (MySQL 1215).
            $table->foreign('project_id', 'fk_project_focus_histories_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'workstream_id'], 'fk_project_focus_histories_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('changed_by_personnel_id', 'fk_project_focus_histories_changer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_focus_histories', 'direction', ['initial', 'forward', 'backward']);
        $this->check('project_focus_histories', 'ck_project_focus_histories_backward_reason', "`direction` <> 'backward' OR `reason` IS NOT NULL");
    }

    private function createStructureTables(): void
    {
        Schema::create('wbs_nodes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $this->code($table, 'wbs_code', 32);
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(1);
            $table->smallInteger('sort_order')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'id'], 'uk_wbs_nodes_project_id');
            $table->unique(['project_id', 'wbs_code'], 'uk_wbs_nodes_project_code');
            $table->foreign('project_id', 'fk_wbs_nodes_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'parent_id'], 'fk_wbs_nodes_parent_agg')
                ->references(['project_id', 'id'])->on('wbs_nodes')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('wbs_nodes', 'status', ['active', 'closed']);

        Schema::create('cbs_nodes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $this->code($table, 'cost_code', 32);
            $table->string('name');
            $this->status($table, 'cost_category');
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'id'], 'uk_cbs_nodes_project_id');
            $table->unique(['project_id', 'cost_code'], 'uk_cbs_nodes_project_code');
            $table->foreign('project_id', 'fk_cbs_nodes_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'parent_id'], 'fk_cbs_nodes_parent_agg')
                ->references(['project_id', 'id'])->on('cbs_nodes')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('cbs_nodes', 'cost_category', self::COST_CATEGORIES);
        $this->enumCheck('cbs_nodes', 'status', ['active', 'closed']);

        Schema::create('wbs_cbs_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('wbs_node_id');
            $table->unsignedBigInteger('cbs_node_id');
            $table->decimal('allocation_pct', 7, 4)->default(100);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['wbs_node_id', 'cbs_node_id'], 'uk_wbs_cbs_mappings_pair');
            $table->foreign('wbs_node_id', 'fk_wbs_cbs_mappings_wbs_node')
                ->references('id')->on('wbs_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('cbs_node_id', 'fk_wbs_cbs_mappings_cbs_node')
                ->references('id')->on('cbs_nodes')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('wbs_cbs_mappings', 'ck_wbs_cbs_mappings_pct_range', '`allocation_pct` > 0 AND `allocation_pct` <= 100');

        Schema::create('work_packages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_workstream_id');
            $table->unsignedBigInteger('project_id');
            $this->code($table, 'package_code', 32);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('wbs_node_id')->nullable();
            $table->unsignedBigInteger('owner_personnel_id');
            $this->status($table)->default('planned');
            $table->date('planned_start_on')->nullable();
            $table->date('planned_finish_on')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'id'], 'uk_work_packages_project_id');
            $table->unique(['project_workstream_id', 'package_code'], 'uk_work_packages_workstream_code');
            $table->foreign(['project_id', 'project_workstream_id'], 'fk_work_packages_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_id', 'fk_work_packages_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'wbs_node_id'], 'fk_work_packages_wbs_node_agg')
                ->references(['project_id', 'id'])->on('wbs_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_work_packages_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('work_packages', 'status', ['planned', 'ready', 'active', 'completed', 'cancelled']);

        Schema::create('work_package_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('predecessor_package_id');
            $table->unsignedBigInteger('successor_package_id');
            $this->status($table, 'dependency_type')->default('FS');
            $table->boolean('is_hard')->default(true);
            $this->auditCreated($table);

            $table->unique(['predecessor_package_id', 'successor_package_id'], 'uk_work_package_dependencies_pair');
            $table->foreign('predecessor_package_id', 'fk_work_package_dependencies_predecessor')
                ->references('id')->on('work_packages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('successor_package_id', 'fk_work_package_dependencies_successor')
                ->references('id')->on('work_packages')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('work_package_dependencies', 'dependency_type', ['FS', 'SS', 'FF', 'SF']);
        $this->check('work_package_dependencies', 'ck_work_package_dependencies_no_self_link', '`predecessor_package_id` <> `successor_package_id`');
    }

    private function createStageInstanceTables(): void
    {
        Schema::create('project_stage_instances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('stage_node_id');
            $table->unsignedBigInteger('owner_personnel_id');
            $this->status($table)->default('not_started');
            $this->ts($table, 'entered_at')->nullable();
            $this->ts($table, 'ready_at')->nullable();
            $this->ts($table, 'passed_at')->nullable();
            $table->date('condition_due_on')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'id'], 'uk_project_stage_instances_project_id');
            $table->unique(['project_id', 'stage_node_id'], 'uk_project_stage_instances_project_node');
            $table->foreign('project_id', 'fk_project_stage_instances_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('stage_node_id', 'fk_project_stage_instances_stage_node')
                ->references('id')->on('stage_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_project_stage_instances_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_stage_instances', 'status', [
            'not_started', 'preparing', 'ready_for_review', 'approval_pending', 'passed', 'conditionally_passed', 'rejected', 'reopened',
        ]);

        Schema::create('project_stage_requirements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_stage_instance_id');
            $table->unsignedBigInteger('requirement_definition_id');
            $this->code($table, 'requirement_code_snapshot', 32);
            $table->string('name_snapshot_tr');
            $table->string('name_snapshot_en');
            $this->status($table, 'evidence_type_snapshot');
            $table->boolean('is_mandatory_snapshot')->default(true);
            $this->status($table, 'applicability')->default('applicable');
            $table->unsignedBigInteger('owner_personnel_id')->nullable();
            $this->ts($table, 'due_at')->nullable();
            $this->status($table)->default('pending');
            $table->text('outcome_note')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_stage_instance_id', 'requirement_code_snapshot'], 'uk_psr_instance_code');
            $table->foreign('project_stage_instance_id', 'fk_psr_stage_instance')
                ->references('id')->on('project_stage_instances')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('requirement_definition_id', 'fk_psr_requirement_definition')
                ->references('id')->on('stage_requirement_definitions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_psr_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_stage_requirements', 'evidence_type_snapshot', [
            'document', 'approval', 'checklist', 'measurement', 'external_check', 'handoff',
        ]);
        $this->enumCheck('project_stage_requirements', 'applicability', ['applicable', 'not_applicable']);
        $this->enumCheck('project_stage_requirements', 'status', ['pending', 'submitted', 'accepted', 'rejected', 'waived']);

        Schema::create('stage_evidence', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_stage_requirement_id');
            $table->unsignedBigInteger('document_revision_id');
            $this->ascii($table, 'evidence_hash', 64);
            $table->unsignedBigInteger('submitted_by_personnel_id');
            $this->ts($table, 'submitted_at');
            $table->unsignedBigInteger('accepted_by_personnel_id')->nullable();
            $this->ts($table, 'accepted_at')->nullable();
            $this->auditCreated($table);

            $table->unique(['project_stage_requirement_id', 'document_revision_id'], 'uk_stage_evidence_requirement_revision');
            $table->foreign('project_stage_requirement_id', 'fk_stage_evidence_requirement')
                ->references('id')->on('project_stage_requirements')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_stage_evidence_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('submitted_by_personnel_id', 'fk_stage_evidence_submitter')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('accepted_by_personnel_id', 'fk_stage_evidence_acceptor')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('stage_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_stage_instance_id');
            $table->unsignedBigInteger('reviewer_personnel_id');
            $this->status($table, 'decision');
            $this->ascii($table, 'reviewed_hash', 64);
            $table->text('conditions')->nullable();
            $table->text('comment')->nullable();
            $this->ts($table, 'decided_at');
            $this->auditCreated($table);

            $table->index(['project_stage_instance_id', 'decided_at'], 'ix_stage_reviews_instance_decided');
            $table->foreign('project_stage_instance_id', 'fk_stage_reviews_stage_instance')
                ->references('id')->on('project_stage_instances')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('reviewer_personnel_id', 'fk_stage_reviews_reviewer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('stage_reviews', 'decision', ['passed', 'conditionally_passed', 'rejected']);
        $this->check('stage_reviews', 'ck_stage_reviews_conditions_required', "`decision` <> 'conditionally_passed' OR `conditions` IS NOT NULL");

        Schema::create('stage_waivers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_stage_instance_id');
            $table->unsignedBigInteger('project_stage_requirement_id')->nullable();
            $table->unsignedBigInteger('approved_by_personnel_id');
            $table->unsignedBigInteger('risk_owner_personnel_id');
            $table->text('reason');
            $table->date('remediation_due_on');
            $this->ts($table, 'granted_at');
            $this->auditCreated($table);

            $table->foreign('project_stage_instance_id', 'fk_stage_waivers_stage_instance')
                ->references('id')->on('project_stage_instances')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_stage_requirement_id', 'fk_stage_waivers_requirement')
                ->references('id')->on('project_stage_requirements')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_stage_waivers_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('risk_owner_personnel_id', 'fk_stage_waivers_risk_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    private function createDepartmentHandoffTables(): void
    {
        Schema::create('department_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('source_workstream_id');
            $table->unsignedBigInteger('target_workstream_id');
            $table->unsignedBigInteger('trigger_stage_instance_id');
            $this->status($table)->default('preparing');
            $this->ts($table, 'sla_due_at')->nullable();
            $table->unsignedBigInteger('accepted_version_id')->nullable();
            $table->unsignedBigInteger('accepted_by_personnel_id')->nullable();
            $this->ts($table, 'accepted_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->foreign('project_id', 'fk_department_handoffs_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'source_workstream_id'], 'fk_department_handoffs_source_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'target_workstream_id'], 'fk_department_handoffs_target_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'trigger_stage_instance_id'], 'fk_department_handoffs_trigger_agg')
                ->references(['project_id', 'id'])->on('project_stage_instances')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('accepted_by_personnel_id', 'fk_department_handoffs_acceptor')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('department_handoffs', 'status', ['preparing', 'in_review', 'accepted', 'rejected', 'cancelled']);
        $this->check('department_handoffs', 'ck_department_handoffs_no_self_link', '`source_workstream_id` <> `target_workstream_id`');

        Schema::create('department_handoff_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('department_handoff_id');
            $table->unsignedInteger('version_no');
            $table->json('manifest_snapshot');
            $this->ascii($table, 'snapshot_hash', 64);
            $this->status($table)->default('draft');
            $table->unsignedBigInteger('submitted_by_personnel_id')->nullable();
            $this->ts($table, 'submitted_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['department_handoff_id', 'version_no'], 'uk_dhv_handoff_version_no');
            $table->unique(['department_handoff_id', 'id'], 'uk_dhv_handoff_id');
            $table->foreign('department_handoff_id', 'fk_dhv_handoff')
                ->references('id')->on('department_handoffs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('submitted_by_personnel_id', 'fk_dhv_submitter')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('department_handoff_versions', 'status', ['draft', 'submitted', 'accepted', 'rejected', 'superseded']);

        Schema::create('department_handoff_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_version_id');
            $this->code($table, 'item_code', 32);
            $this->status($table, 'item_type');
            $table->text('description');
            $table->unsignedBigInteger('document_revision_id')->nullable();
            $this->status($table, 'completion_state')->default('pending');
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['handoff_version_id', 'item_code'], 'uk_dhi_version_code');
            $table->foreign('handoff_version_id', 'fk_dhi_version')
                ->references('id')->on('department_handoff_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_dhi_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('department_handoff_items', 'item_type', ['document', 'evidence', 'open_issue', 'checklist', 'material', 'quantity']);
        $this->enumCheck('department_handoff_items', 'completion_state', ['pending', 'complete', 'waived', 'not_applicable']);

        Schema::create('department_handoff_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_version_id');
            $table->unsignedBigInteger('reviewer_personnel_id');
            $this->status($table, 'decision');
            $table->text('comment')->nullable();
            $this->ts($table, 'decided_at');
            $this->auditCreated($table);

            $table->index(['handoff_version_id', 'decided_at'], 'ix_dhr_version_decided');
            $table->foreign('handoff_version_id', 'fk_dhr_version')
                ->references('id')->on('department_handoff_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('reviewer_personnel_id', 'fk_dhr_reviewer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('department_handoff_reviews', 'decision', ['accepted', 'rejected', 'returned']);
    }

    private function createControlTables(): void
    {
        Schema::create('schedule_baselines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('version_no');
            $table->string('name');
            $this->status($table, 'source')->default('manual');
            $table->unsignedBigInteger('baseline_document_revision_id')->nullable();
            $table->date('planned_start_on');
            $table->date('planned_finish_on');
            $this->status($table)->default('draft');
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->ts($table, 'approved_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'version_no'], 'uk_schedule_baselines_project_version_no');
            $table->foreign('project_id', 'fk_schedule_baselines_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('baseline_document_revision_id', 'fk_schedule_baselines_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_schedule_baselines_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('schedule_baselines', 'source', ['manual', 'ms_project_import']);
        $this->enumCheck('schedule_baselines', 'status', ['draft', 'approved', 'superseded']);
        $this->check('schedule_baselines', 'ck_schedule_baselines_date_order', '`planned_finish_on` >= `planned_start_on`');

        Schema::create('milestones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $this->code($table, 'milestone_code', 32);
            $table->string('name');
            $this->status($table, 'milestone_kind')->default('internal');
            $table->unsignedBigInteger('wbs_node_id')->nullable();
            $table->unsignedBigInteger('contract_milestone_id')->nullable();
            $this->ts($table, 'planned_at');
            $this->ts($table, 'baseline_at')->nullable();
            $this->ts($table, 'forecast_at')->nullable();
            $this->ts($table, 'actual_at')->nullable();
            $this->status($table)->default('planned');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'milestone_code'], 'uk_milestones_project_code');
            $table->foreign('project_id', 'fk_milestones_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'wbs_node_id'], 'fk_milestones_wbs_node_agg')
                ->references(['project_id', 'id'])->on('wbs_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('contract_milestone_id', 'fk_milestones_contract_milestone')
                ->references('id')->on('contract_milestones')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('milestones', 'milestone_kind', ['contractual', 'internal', 'payment', 'gate']);
        $this->enumCheck('milestones', 'status', ['planned', 'at_risk', 'achieved', 'missed', 'cancelled']);

        Schema::create('progress_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $this->ts($table, 'snapshot_at');
            $this->status($table, 'source')->default('manual');
            $table->decimal('physical_progress_pct', 7, 4);
            $table->decimal('planned_progress_pct', 7, 4)->nullable();
            $table->decimal('cost_progress_pct', 7, 4)->nullable();
            $table->unsignedBigInteger('reported_by_personnel_id');
            $this->auditCreated($table);

            $table->unique(['project_id', 'snapshot_at', 'source'], 'uk_progress_snapshots_project_at_source');
            $table->foreign('project_id', 'fk_progress_snapshots_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('reported_by_personnel_id', 'fk_progress_snapshots_reporter')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('progress_snapshots', 'source', ['manual', 'report', 'computed']);
        $this->check('progress_snapshots', 'ck_progress_snapshots_pct_range', '`physical_progress_pct` >= 0 AND `physical_progress_pct` <= 100'
            .' AND (`planned_progress_pct` IS NULL OR (`planned_progress_pct` >= 0 AND `planned_progress_pct` <= 100))'
            .' AND (`cost_progress_pct` IS NULL OR (`cost_progress_pct` >= 0 AND `cost_progress_pct` <= 100))');

        Schema::create('project_issues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('workstream_id')->nullable();
            $this->code($table, 'issue_no', 32);
            $table->string('title');
            $table->text('description');
            $this->status($table, 'severity')->default('medium');
            $this->status($table)->default('open');
            $table->unsignedBigInteger('owner_personnel_id');
            $table->unsignedBigInteger('raised_by_personnel_id');
            $this->ts($table, 'raised_at');
            $this->ts($table, 'due_at')->nullable();
            $this->ts($table, 'resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $this->code($table, 'source_type', 32)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'issue_no'], 'uk_project_issues_project_no');
            $table->foreign('project_id', 'fk_project_issues_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'workstream_id'], 'fk_project_issues_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_project_issues_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('raised_by_personnel_id', 'fk_project_issues_raiser')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_issues', 'severity', ['low', 'medium', 'high', 'critical']);
        $this->enumCheck('project_issues', 'status', ['open', 'in_progress', 'resolved', 'closed', 'cancelled']);

        Schema::create('project_risks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('workstream_id')->nullable();
            $this->code($table, 'risk_no', 32);
            $table->string('title');
            $table->text('description');
            $this->status($table, 'category');
            $table->decimal('probability', 9, 6);
            $table->unsignedTinyInteger('impact');

            if ($this->isMySql()) {
                $table->decimal('score', 9, 6)->storedAs('`probability` * `impact`');
            } else {
                $table->decimal('score', 9, 6)->nullable();
            }

            $this->status($table, 'response_strategy')->default('mitigate');
            $table->text('mitigation_plan')->nullable();
            $table->unsignedBigInteger('owner_personnel_id');
            $this->status($table)->default('identified');
            $table->date('review_due_on')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'risk_no'], 'uk_project_risks_project_no');
            $table->foreign('project_id', 'fk_project_risks_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'workstream_id'], 'fk_project_risks_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_project_risks_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_risks', 'category', ['technical', 'commercial', 'schedule', 'hse', 'supply', 'regulatory', 'financial']);
        $this->enumCheck('project_risks', 'response_strategy', ['avoid', 'mitigate', 'transfer', 'accept']);
        $this->enumCheck('project_risks', 'status', ['identified', 'assessed', 'mitigating', 'closed', 'materialized']);
        $this->check('project_risks', 'ck_project_risks_ratio_range', '`probability` >= 0 AND `probability` <= 1');
        $this->check('project_risks', 'ck_project_risks_impact_range', '`impact` BETWEEN 1 AND 5');

        Schema::create('delay_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('workstream_id')->nullable();
            $this->ts($table, 'detected_at');
            $table->integer('delay_days');
            $this->status($table, 'cause_category');
            $table->text('description');
            $table->boolean('is_excusable')->nullable();
            $table->unsignedBigInteger('evidence_document_revision_id')->nullable();
            $table->unsignedBigInteger('reported_by_personnel_id');
            $this->status($table)->default('open');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['project_id', 'detected_at'], 'ix_delay_events_project_detected');
            $table->foreign('project_id', 'fk_delay_events_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'workstream_id'], 'fk_delay_events_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('evidence_document_revision_id', 'fk_delay_events_evidence_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('reported_by_personnel_id', 'fk_delay_events_reporter')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('delay_events', 'cause_category', [
            'customer', 'supplier', 'internal', 'weather', 'regulatory', 'design', 'force_majeure', 'other',
        ]);
        $this->enumCheck('delay_events', 'status', ['open', 'mitigating', 'absorbed', 'claimed', 'closed']);
        $this->check('delay_events', 'ck_delay_events_days_non_negative', '`delay_days` >= 0');

        Schema::create('recovery_actions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('delay_event_id');
            $table->unsignedBigInteger('owner_personnel_id');
            $table->text('description');
            $table->smallInteger('expected_recovery_days')->nullable();
            $this->ts($table, 'due_at');
            $this->status($table)->default('planned');
            $this->ts($table, 'completed_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->foreign('delay_event_id', 'fk_recovery_actions_delay_event')
                ->references('id')->on('delay_events')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_recovery_actions_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('recovery_actions', 'status', ['planned', 'in_progress', 'done', 'cancelled']);

        Schema::create('project_changes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $this->code($table, 'change_no', 32);
            $this->status($table, 'change_type');
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('personnel_id');
            $this->ts($table, 'requested_at');
            $this->code($table, 'source_type', 32)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('impact_cost', 20, 4)->nullable();
            $this->asciiChar($table, 'currency_code', 3)->nullable();
            $table->smallInteger('impact_days')->nullable();
            $table->boolean('affects_baseline')->default(false);
            $this->status($table)->default('draft');
            $this->ts($table, 'approved_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'change_no'], 'uk_project_changes_project_no');
            $table->foreign('project_id', 'fk_project_changes_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_project_changes_requester')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_project_changes_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_changes', 'change_type', ['scope', 'schedule', 'cost', 'technical', 'contract_variation']);
        $this->enumCheck('project_changes', 'status', ['draft', 'evaluating', 'approved', 'rejected', 'implemented', 'cancelled']);

        Schema::create('commercial_clarifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $this->code($table, 'clarification_no', 32);
            $this->status($table, 'clarification_type');
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('raised_by_personnel_id');
            $table->unsignedBigInteger('customer_contact_party_id')->nullable();
            $this->status($table)->default('open');
            $table->text('response')->nullable();
            $this->ts($table, 'responded_at')->nullable();
            $table->unsignedBigInteger('linked_change_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'clarification_no'], 'uk_commercial_clarifications_project_no');
            $table->foreign('project_id', 'fk_commercial_clarifications_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('raised_by_personnel_id', 'fk_commercial_clarifications_raiser')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('customer_contact_party_id', 'fk_commercial_clarifications_customer_contact')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('linked_change_id', 'fk_commercial_clarifications_linked_change')
                ->references('id')->on('project_changes')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('commercial_clarifications', 'clarification_type', ['scope_interpretation', 'additional_work', 'price_adjustment', 'contract_qa']);
        $this->enumCheck('commercial_clarifications', 'status', ['open', 'under_review', 'answered', 'closed', 'converted_to_change']);

        Schema::create('commercial_exposures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $this->code($table, 'exposure_no', 32);
            $table->unsignedBigInteger('cbs_node_id')->nullable();
            $this->status($table, 'exposure_kind');
            $table->text('description');
            $table->decimal('exposure_amount', 20, 4);
            $this->asciiChar($table, 'currency_code', 3);
            $table->decimal('probability', 9, 6)->nullable();
            $table->unsignedBigInteger('owner_personnel_id');
            $table->unsignedBigInteger('source_delay_event_id')->nullable();
            $table->unsignedBigInteger('source_change_id')->nullable();
            $this->status($table)->default('identified');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'exposure_no'], 'uk_commercial_exposures_project_no');
            $table->foreign('project_id', 'fk_commercial_exposures_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'cbs_node_id'], 'fk_commercial_exposures_cbs_node_agg')
                ->references(['project_id', 'id'])->on('cbs_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_commercial_exposures_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_commercial_exposures_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('source_delay_event_id', 'fk_commercial_exposures_source_delay_event')
                ->references('id')->on('delay_events')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('source_change_id', 'fk_commercial_exposures_source_change')
                ->references('id')->on('project_changes')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('commercial_exposures', 'exposure_kind', ['loss', 'penalty', 'claim', 'unbilled_work', 'disputed_amount']);
        $this->enumCheck('commercial_exposures', 'status', ['identified', 'assessed', 'recovering', 'recovered', 'written_off', 'closed']);
        $this->check('commercial_exposures', 'ck_commercial_exposures_ratio_range', '`probability` IS NULL OR (`probability` >= 0 AND `probability` <= 1)');

        Schema::create('project_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $this->code($table, 'decision_no', 32);
            $this->status($table, 'decision_scope')->default('other');
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('personnel_id');
            $this->ts($table, 'decided_at');
            $table->unsignedBigInteger('document_revision_id')->nullable();
            $this->auditCreated($table);

            $table->unique(['project_id', 'decision_no'], 'uk_project_decisions_project_no');
            $table->foreign('project_id', 'fk_project_decisions_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_project_decisions_decider')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_project_decisions_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_decisions', 'decision_scope', ['gate', 'change', 'commercial', 'technical', 'focus', 'other']);
    }

    /**
     * Karsilikli FK'lar ve DMS'in ertelenmis `project_id` kolonlari.
     */
    private function addCrossReferences(): void
    {
        Schema::table('stage_templates', function (Blueprint $table): void {
            $table->foreign(['id', 'current_version_id'], 'fk_stage_templates_current_version_agg')
                ->references(['stage_template_id', 'id'])->on('stage_template_versions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreign(['id', 'primary_focus_workstream_id'], 'fk_projects_primary_focus_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('department_handoffs', function (Blueprint $table): void {
            $table->foreign(['id', 'accepted_version_id'], 'fk_department_handoffs_accepted_version_agg')
                ->references(['department_handoff_id', 'id'])->on('department_handoff_versions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable()->after('owner_org_unit_id');
            $table->foreign('project_id', 'fk_documents_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('transmittals', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable()->after('transmittal_no');
            $table->foreign('project_id', 'fk_transmittals_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('transmittals', function (Blueprint $table): void {
            $table->dropForeign('fk_transmittals_project');
            $table->dropColumn('project_id');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign('fk_documents_project');
            $table->dropColumn('project_id');
        });

        foreach (array_reverse(self::AUDITED_TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id', 'archived_by_personnel_id'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropForeign($this->fkName($table, $column));
                    }
                }
            });
        }

        Schema::table('department_handoffs', fn (Blueprint $table) => $table->dropForeign('fk_department_handoffs_accepted_version_agg'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropForeign('fk_projects_primary_focus_workstream_agg'));
        Schema::table('stage_templates', fn (Blueprint $table) => $table->dropForeign('fk_stage_templates_current_version_agg'));

        foreach (array_reverse(self::AUDITED_TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
