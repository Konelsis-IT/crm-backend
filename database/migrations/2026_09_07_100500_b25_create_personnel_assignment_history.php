<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B25 - Organizasyon birimi ve gorev gecmisi.
 *
 * personnel_assignments yalniz gecmisi tutan eklemeli bir kayittir: bir
 * personelin org_unit_id veya job_title'i degistiginde otomatik yeni satir
 * acilir, oncekini kapatir (PersonnelService/SyncPersonnelAssignment).
 * Ekranda ayri bir form yoktur; personelin kartinin altinda salt okunur
 * bir listedir.
 *
 * Dogrudan amir gecmisi burada degil B03'teki reporting_relationships'te
 * tutulur (D-62): once (D-61) bu tabloda birlikte tutuluyordu, tam
 * organizasyon tasarimina gecince ayrildi.
 *
 * personnel_assignments'ta ayni personel icin en fazla bir acik
 * (effective_to NULL) satir olabilir: open_guard uretilen kolonu ve
 * UNIQUE indeksi bunu garanti eder.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('personnel_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $table->string('job_title')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('note')->nullable();

            if ($this->isMySql()) {
                $table->unsignedBigInteger('open_guard')
                    ->storedAs('CASE WHEN `effective_to` IS NULL THEN `personnel_id` END')
                    ->nullable();
            } else {
                $table->unsignedBigInteger('open_guard')->nullable();
            }

            $this->auditCreated($table);

            $table->unique('open_guard', 'uk_personnel_assignments_open');
            $table->index(['personnel_id', 'effective_from'], 'ix_personnel_assignments_personnel');
            // restrictOnDelete: personnel_id, open_guard'in taban kolonu; MySQL uretilmis
            // (generated) kolonun tabanina ON DELETE CASCADE'e izin vermez (hata 1215).
            $table->foreign('personnel_id', 'fk_personnel_assignments_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('org_unit_id', 'fk_personnel_assignments_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->validRangeCheck('personnel_assignments', 'effective_from', 'effective_to');

        $this->personnelForeignKeys('personnel_assignments');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('personnel_assignments', function (Blueprint $table): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id', 'archived_by_personnel_id'] as $column) {
                if (Schema::hasColumn('personnel_assignments', $column)) {
                    $table->dropForeign($this->fkName('personnel_assignments', $column));
                }
            }

            $table->dropForeign('fk_personnel_assignments_org_unit');
            $table->dropForeign('fk_personnel_assignments_personnel');
        });

        Schema::dropIfExists('personnel_assignments');
    }
};
