<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B02 - Yetkinlik tablolari; ardindan B00/B01 tablolarinin personel iz
 * kolonlarina foreign key eklenir.
 *
 * Departman/organizasyon birimi burada degil B03'te (org_units) kurulur;
 * onceki surumde burada olusturulan `departments` tablosu D-61/D-62 ile
 * kaldirildi.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'personnel',
        'countries',
        'currencies',
        'units_of_measure',
        'security_classifications',
        'retention_policies',
        'organizations',
        'legal_entities',
        'business_calendars',
        'business_calendar_weekdays',
        'calendar_days',
        'competencies',
        'personnel_competencies',
    ];

    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name');
            $this->status($table, 'category');
            $table->text('description')->nullable();
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_competencies_code');
            $table->index(['category', 'name'], 'ix_competencies_category_name');
        });
        $this->enumCheck('competencies', 'category', [
            'electrical', 'automation', 'software', 'mechanical', 'civil',
            'field', 'commercial', 'management', 'safety', 'language',
        ]);
        $this->enumCheck('competencies', 'status', ['active', 'inactive']);

        Schema::create('personnel_competencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('competency_id');
            $this->status($table, 'level')->default('intermediate');
            $table->string('note')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['personnel_id', 'competency_id'], 'uk_personnel_competencies_pair');
            $table->foreign('personnel_id', 'fk_personnel_competencies_personnel')
                ->references('id')->on('personnel')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('competency_id', 'fk_personnel_competencies_competency')
                ->references('id')->on('competencies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('personnel_competencies', 'level', ['beginner', 'intermediate', 'advanced', 'expert']);

        Schema::table('business_number_allocations', function (Blueprint $table): void {
            $table->foreign('allocated_by_personnel_id', 'fk_number_allocations_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
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

        Schema::table('business_number_allocations', function (Blueprint $table): void {
            $table->dropForeign('fk_number_allocations_personnel');
        });

        Schema::dropIfExists('personnel_competencies');
        Schema::dropIfExists('competencies');
    }
};
