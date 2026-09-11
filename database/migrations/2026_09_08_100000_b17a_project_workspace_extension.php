<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B17A - Proje calisma alani uzantisi (docs/planning/11 SS1.10-1.13; karar D-68).
 *
 * Kullanici talebi (8 Eylul 2026): proje ayri bir calisma alani olarak
 * gorulmeli, hangi adimda oldugu net okunmali; adres ve fotograf eklenebilmeli;
 * tekliften projeye donusturulebilmeli; gecmiste yapilmis/aktif projeler
 * dogrudan olusturulabilmeli. Bu yuzden:
 *
 * - `projects.accepted_handoff_version_id` NULL olabilir; `origin` kolonu
 *   projenin nasil dogdugunu soyler (`handoff`, `converted`, `direct`).
 *   Kanonik zincir korunur: her projenin bir business case'i ve PRJ kodu vardir.
 * - `projects` saha adresi kolonlari (tam adres + koordinat) ve aciklama alir.
 * - `project_photos`: saha fotograflari (DMS `file_objects` uzerinden).
 * - `project_supply_items`: satin alma/lojistik/yazilim adimlarinin urun,
 *   hizmet ve yazilim kalemleri (B18 katalogu gelene kadar serbest metin).
 * - `project_team_members`: projede calisan personel ve rolu (saha adimi).
 * - `focus_expectations`: her operasyon grubunun (odak adiminin) projeden
 *   bekledigi veri; ilerleme kontrol listesi ve odak gecis guard'i buradan
 *   okunur (App\Query\Project\ProjectStepReadiness).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'focus_expectations', 'project_photos', 'project_supply_items', 'project_team_members',
    ];

    /** @var list<string> */
    private const EXPECTATION_KINDS = [
        'site_address', 'planned_dates', 'components', 'documents', 'drawings', 'photos', 'wbs', 'milestones',
        'schedule_baseline', 'supply_items', 'supply_ordered', 'supply_delivered', 'cbs', 'exposures',
        'team_members', 'work_packages', 'progress', 'software_items', 'automation_documents',
    ];

    public function up(): void
    {
        $this->extendProjects();
        $this->createFocusExpectations();
        $this->createProjectPhotos();
        $this->createProjectSupplyItems();
        $this->createProjectTeamMembers();

        foreach (self::AUDITED_TABLES as $table) {
            $this->personnelForeignKeys($table);
        }
    }

    private function extendProjects(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // Dogrudan olusturulan ve tekliften donusturulen projelerde devir surumu olmayabilir.
            $table->unsignedBigInteger('accepted_handoff_version_id')->nullable()->change();

            $this->status($table, 'origin')->default('handoff');
            $table->string('legacy_reference', 64)->nullable();
            $table->text('description')->nullable();

            $table->string('site_address_line1')->nullable();
            $table->string('site_address_line2')->nullable();
            $table->string('site_district', 100)->nullable();
            $table->string('site_city', 100)->nullable();
            $this->ascii($table, 'site_postal_code', 16)->nullable();
            $this->asciiChar($table, 'site_country_code', 2)->nullable();
            $table->decimal('site_latitude', 10, 7)->nullable();
            $table->decimal('site_longitude', 10, 7)->nullable();
            $table->text('site_note')->nullable();

            $table->foreign('site_country_code', 'fk_projects_site_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
        });

        $this->enumCheck('projects', 'origin', ['handoff', 'converted', 'direct']);
        $this->check('projects', 'ck_projects_site_latitude', '`site_latitude` IS NULL OR (`site_latitude` BETWEEN -90 AND 90)');
        $this->check('projects', 'ck_projects_site_longitude', '`site_longitude` IS NULL OR (`site_longitude` BETWEEN -180 AND 180)');
    }

    private function createFocusExpectations(): void
    {
        Schema::create('focus_expectations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_definition_id');
            $this->code($table, 'code', 32);
            $table->string('name_tr');
            $table->string('name_en');
            $this->code($table, 'kind', 32);
            $table->unsignedSmallInteger('min_count')->default(1);
            $table->boolean('is_mandatory')->default(true);
            $table->text('help_tr')->nullable();
            $table->text('help_en')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['group_definition_id', 'code'], 'uk_focus_expectations_group_code');
            $table->foreign('group_definition_id', 'fk_focus_expectations_group')
                ->references('id')->on('operation_group_definitions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('focus_expectations', 'kind', self::EXPECTATION_KINDS);
        $this->enumCheck('focus_expectations', 'status', ['active', 'inactive']);
        $this->check('focus_expectations', 'ck_focus_expectations_min_count', '`min_count` >= 1');
    }

    private function createProjectPhotos(): void
    {
        Schema::create('project_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('file_object_id');
            $table->unsignedBigInteger('workstream_id')->nullable();
            $table->string('caption')->nullable();
            $table->date('taken_on')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'file_object_id'], 'uk_project_photos_project_file');
            $table->index(['project_id', 'sort_order'], 'ix_project_photos_project_sort');
            $table->foreign('project_id', 'fk_project_photos_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('file_object_id', 'fk_project_photos_file')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'workstream_id'], 'fk_project_photos_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    private function createProjectSupplyItems(): void
    {
        Schema::create('project_supply_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('workstream_id')->nullable();
            $this->status($table, 'item_kind')->default('product');
            $this->code($table, 'item_code', 64)->nullable();
            $table->string('name');
            $table->text('specification')->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('unit_cost', 20, 4)->nullable();
            $this->asciiChar($table, 'currency_code', 3);
            $table->unsignedBigInteger('supplier_party_id')->nullable();
            $table->unsignedBigInteger('wbs_node_id')->nullable();
            $table->date('needed_on')->nullable();
            $table->date('ordered_on')->nullable();
            $table->date('expected_delivery_on')->nullable();
            $table->date('delivered_on')->nullable();
            $this->status($table)->default('planned');
            $table->text('note')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['project_id', 'status'], 'ix_project_supply_items_project_status');
            $table->index(['project_id', 'item_kind'], 'ix_project_supply_items_project_kind');
            $table->foreign('project_id', 'fk_project_supply_items_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'workstream_id'], 'fk_project_supply_items_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'wbs_node_id'], 'fk_project_supply_items_wbs_agg')
                ->references(['project_id', 'id'])->on('wbs_nodes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('uom_id', 'fk_project_supply_items_uom')
                ->references('id')->on('units_of_measure')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_project_supply_items_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('supplier_party_id', 'fk_project_supply_items_supplier')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_supply_items', 'item_kind', ['product', 'service', 'software']);
        $this->enumCheck('project_supply_items', 'status', ['planned', 'requested', 'ordered', 'shipped', 'delivered', 'installed', 'cancelled']);
        $this->check('project_supply_items', 'ck_project_supply_items_quantity', '`quantity` > 0');
        $this->check('project_supply_items', 'ck_project_supply_items_unit_cost', '`unit_cost` IS NULL OR `unit_cost` >= 0');
    }

    private function createProjectTeamMembers(): void
    {
        Schema::create('project_team_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('workstream_id')->nullable();
            $this->status($table, 'team_role')->default('other');
            $table->decimal('allocation_pct', 5, 2)->default(100);
            $table->date('assigned_from');
            $table->date('assigned_until')->nullable();
            $table->boolean('is_lead')->default(false);
            $this->status($table)->default('active');
            $table->text('note')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'personnel_id', 'team_role'], 'uk_project_team_members_project_person_role');
            $table->index(['personnel_id', 'status'], 'ix_project_team_members_person_status');
            $table->foreign('project_id', 'fk_project_team_members_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_project_team_members_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['project_id', 'workstream_id'], 'fk_project_team_members_workstream_agg')
                ->references(['project_id', 'id'])->on('project_workstreams')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_team_members', 'team_role', [
            'project_manager', 'site_manager', 'engineer', 'technician', 'procurement_officer', 'accountant',
            'logistics_officer', 'software_engineer', 'qa_qc', 'hse_officer', 'other',
        ]);
        $this->enumCheck('project_team_members', 'status', ['active', 'ended']);
        $this->check('project_team_members', 'ck_project_team_members_allocation', '`allocation_pct` > 0 AND `allocation_pct` <= 100');
        $this->check('project_team_members', 'ck_project_team_members_assigned_range', '`assigned_until` IS NULL OR `assigned_until` >= `assigned_from`');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

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

        Schema::dropIfExists('project_team_members');
        Schema::dropIfExists('project_supply_items');
        Schema::dropIfExists('project_photos');
        Schema::dropIfExists('focus_expectations');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign('fk_projects_site_country');
            $table->dropColumn([
                'origin', 'legacy_reference', 'description', 'site_address_line1', 'site_address_line2',
                'site_district', 'site_city', 'site_postal_code', 'site_country_code', 'site_latitude',
                'site_longitude', 'site_note',
            ]);
        });
    }
};
