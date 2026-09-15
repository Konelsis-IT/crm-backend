<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B26 - Personel unvanlari (D-88, 15 Eylul 2026, kullanici talimati).
 *
 * "Gorev" (positions.title / personnel.job_title) personelin departmana
 * bagli somut isidir (orn. "Insaat Sorumlusu"); "unvan" ise departmandan
 * bagimsiz, sirket genelinde tekrar eden kademe adidir (orn. "Sorumlu",
 * "Mudur"). Ikisi kasitli olarak ayri tutulur.
 *
 * personnel_titles kucuk bir referans katalogudur (Competency ile ayni
 * bicimde); personnel.title_id bu kataloga NULL edilebilir FK'dir.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('personnel_titles', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name');
            $table->unsignedSmallInteger('rank_level')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_personnel_titles_code');
            $table->index('rank_level', 'ix_personnel_titles_rank_level');
        });
        $this->enumCheck('personnel_titles', 'status', ['active', 'inactive']);
        $this->personnelForeignKeys('personnel_titles');

        Schema::table('personnel', function (Blueprint $table): void {
            $table->unsignedBigInteger('title_id')->nullable()->after('job_title');
            $table->index('title_id', 'ix_personnel_title');
        });

        Schema::table('personnel', function (Blueprint $table): void {
            $table->foreign('title_id', 'fk_personnel_title')
                ->references('id')->on('personnel_titles')->nullOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('personnel', function (Blueprint $table): void {
            $table->dropForeign('fk_personnel_title');
            $table->dropIndex('ix_personnel_title');
            $table->dropColumn('title_id');
        });

        Schema::table('personnel_titles', function (Blueprint $blueprint): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id', 'archived_by_personnel_id'] as $column) {
                if (Schema::hasColumn('personnel_titles', $column)) {
                    $blueprint->dropForeign($this->fkName('personnel_titles', $column));
                }
            }
        });

        Schema::dropIfExists('personnel_titles');
    }
};
