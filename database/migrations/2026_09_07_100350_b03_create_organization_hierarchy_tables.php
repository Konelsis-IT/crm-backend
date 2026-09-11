<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B03 - Organizasyon hiyerarsisi ve pozisyon (docs/planning/06 SS4.1-4.3, 4.7-4.8).
 *
 * Kullanici karariyla (D-62) departman tamamen org_units'e tasindi: mevcut
 * 10 departman OrganizationStructureSeeder'da ayni kod/adla org_units
 * (unit_type=department) olarak yeniden kurulur. `employments`,
 * `personnel_private_profiles`, `teams`, `team_memberships`, `delegations`,
 * `personnel_status_histories` bu batch'te degildir (kapsam disi, D-01
 * kararini beklerler).
 *
 * Iki kasitli sadelestirme (D-62):
 * - org_units.manager_personnel_id: canonical tasarimda yok (fonksiyonel
 *   yoneticilik reporting_relationships uzerinden), burada departmanin eski
 *   davranisiyla ayni kalsin diye denormalize bir kolon olarak tutuldu.
 * - org_unit_relations ve reporting_relationships ekranda dogrudan
 *   duzenlenmez; OrgUnitService ve PersonnelService "guncel deger" secimini
 *   (ust birim, dogrudan amir) bu tarihceli tablolara otomatik yazar/kapatir
 *   (personnel_assignments'taki desenin aynisi).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'org_units',
        'org_unit_relations',
        'positions',
        'position_assignments',
        'reporting_relationships',
    ];

    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $this->code($table, 'code', 32);
            $table->string('name');
            $this->status($table, 'unit_type');
            $this->code($table, 'cost_center_code', 32)->nullable();
            $table->unsignedBigInteger('manager_personnel_id')->nullable();
            $this->status($table)->default('active');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['legal_entity_id', 'code'], 'uk_org_units_legal_entity_code');
            $table->foreign('legal_entity_id', 'fk_org_units_legal_entity')
                ->references('id')->on('legal_entities')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('manager_personnel_id', 'fk_org_units_manager')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('org_units', 'unit_type', [
            'company', 'division', 'department', 'section', 'office', 'site', 'committee',
        ]);
        $this->enumCheck('org_units', 'status', ['planned', 'active', 'inactive', 'dissolved']);
        $this->validRangeCheck('org_units');

        Schema::create('org_unit_relations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_org_unit_id');
            $table->unsignedBigInteger('child_org_unit_id');
            $this->status($table, 'relation_type')->default('hierarchy');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();

            if ($this->isMySql()) {
                $table->unsignedBigInteger('hierarchy_active_guard')
                    ->storedAs("CASE WHEN `relation_type` = 'hierarchy' AND `valid_until` IS NULL THEN `child_org_unit_id` END")
                    ->nullable();
            } else {
                $table->unsignedBigInteger('hierarchy_active_guard')->nullable();
            }

            $this->auditCreated($table);

            $table->unique('hierarchy_active_guard', 'uk_org_unit_relations_active_parent');
            $table->index(['child_org_unit_id', 'relation_type'], 'ix_org_unit_relations_child_active');
            $table->foreign('parent_org_unit_id', 'fk_org_unit_relations_parent')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('child_org_unit_id', 'fk_org_unit_relations_child')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('org_unit_relations', 'relation_type', ['hierarchy', 'functional', 'matrix']);
        $this->validRangeCheck('org_unit_relations');
        $this->check('org_unit_relations', 'ck_org_unit_relations_no_self_link', '`parent_org_unit_id` <> `child_org_unit_id`');

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('org_unit_id');
            $this->code($table, 'code', 32);
            $table->string('title');
            $this->code($table, 'grade', 32)->nullable();
            $table->unsignedTinyInteger('managerial_level')->default(0);
            $table->unsignedSmallInteger('headcount')->default(1);
            $this->status($table)->default('active');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['org_unit_id', 'code'], 'uk_positions_org_unit_code');
            $table->foreign('org_unit_id', 'fk_positions_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('positions', 'status', ['active', 'frozen', 'closed']);
        $this->validRangeCheck('positions');
        $this->check('positions', 'ck_positions_managerial_level', '`managerial_level` <= 5');

        Schema::create('position_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('position_id');
            $table->boolean('is_primary')->default(true);
            $table->decimal('allocation_pct', 5, 2)->default(100);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();

            if ($this->isMySql()) {
                $table->unsignedBigInteger('primary_active_guard')
                    ->storedAs('CASE WHEN `is_primary` = 1 AND `valid_until` IS NULL THEN `personnel_id` END')
                    ->nullable();
            } else {
                $table->unsignedBigInteger('primary_active_guard')->nullable();
            }

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('primary_active_guard', 'uk_position_assignments_active_primary');
            $table->index(['personnel_id', 'valid_from'], 'ix_position_assignments_personnel');
            // restrictOnDelete: personnel_id, primary_active_guard'in taban kolonu; MySQL
            // uretilmis (generated) kolonun tabanina ON DELETE CASCADE'e izin vermez (1215).
            $table->foreign('personnel_id', 'fk_position_assignments_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('position_id', 'fk_position_assignments_position')
                ->references('id')->on('positions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->validRangeCheck('position_assignments');
        $this->check('position_assignments', 'ck_position_assignments_allocation', '`allocation_pct` > 0 AND `allocation_pct` <= 100');

        Schema::create('reporting_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('manager_personnel_id');
            $this->status($table, 'relation_type')->default('line');
            $this->status($table, 'scope_type')->default('all');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();

            if ($this->isMySql()) {
                $table->unsignedBigInteger('line_active_guard')
                    ->storedAs("CASE WHEN `relation_type` = 'line' AND `valid_until` IS NULL THEN `personnel_id` END")
                    ->nullable();
            } else {
                $table->unsignedBigInteger('line_active_guard')->nullable();
            }

            $this->auditCreated($table);

            $table->unique('line_active_guard', 'uk_reporting_relationships_active_line');
            $table->index(['personnel_id', 'valid_from'], 'ix_reporting_relationships_personnel');
            // restrictOnDelete: personnel_id, line_active_guard'in taban kolonu; MySQL
            // uretilmis (generated) kolonun tabanina ON DELETE CASCADE'e izin vermez (1215).
            $table->foreign('personnel_id', 'fk_reporting_relationships_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('manager_personnel_id', 'fk_reporting_relationships_manager')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('reporting_relationships', 'relation_type', ['line', 'functional', 'project']);
        $this->enumCheck('reporting_relationships', 'scope_type', ['all', 'org_unit', 'project', 'functional_area']);
        $this->validRangeCheck('reporting_relationships');
        $this->check('reporting_relationships', 'ck_reporting_relationships_no_self_link', '`personnel_id` <> `manager_personnel_id`');

        Schema::table('personnel', function (Blueprint $table): void {
            $table->foreign('org_unit_id', 'fk_personnel_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
        });

        foreach (self::AUDITED_TABLES as $table) {
            $this->personnelForeignKeys($table);
        }
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

        Schema::table('personnel', function (Blueprint $table): void {
            $table->dropForeign('fk_personnel_org_unit');
        });

        Schema::table('reporting_relationships', function (Blueprint $table): void {
            $table->dropForeign('fk_reporting_relationships_manager');
            $table->dropForeign('fk_reporting_relationships_personnel');
        });
        Schema::dropIfExists('reporting_relationships');

        Schema::table('position_assignments', function (Blueprint $table): void {
            $table->dropForeign('fk_position_assignments_position');
            $table->dropForeign('fk_position_assignments_personnel');
        });
        Schema::dropIfExists('position_assignments');

        Schema::table('positions', function (Blueprint $table): void {
            $table->dropForeign('fk_positions_org_unit');
        });
        Schema::dropIfExists('positions');

        Schema::table('org_unit_relations', function (Blueprint $table): void {
            $table->dropForeign('fk_org_unit_relations_child');
            $table->dropForeign('fk_org_unit_relations_parent');
        });
        Schema::dropIfExists('org_unit_relations');

        Schema::table('org_units', function (Blueprint $table): void {
            $table->dropForeign('fk_org_units_manager');
            $table->dropForeign('fk_org_units_legal_entity');
        });
        Schema::dropIfExists('org_units');
    }
};
