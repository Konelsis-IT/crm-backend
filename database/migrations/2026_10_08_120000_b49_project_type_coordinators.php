<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B49 - Proje tipi koordinatorleri (D-175, 8 Ekim 2026 kullanici talimati:
 * "Proje mudurlerinden bagimsiz olarak proje tipinin tamamini koordine
 * edenler var. Ornegin GES hakkindaki tum projelerle ilgilenmesi, gorusmesi
 * gereken kisi Ertugrul ama o proje muduru degil. Sisteme bir Proje tipi
 * koordinatoru ekleyelim").
 *
 * `project_type_coordinators`: bir proje tipinin (ProjectScopeType: ges, res,
 * tm, hes, bes, enh_eih) koordinatoru olan personel. Satir bir atamadir:
 * `valid_from` atandigi an, `valid_until` gorevin bittigi an (bos = gecerli).
 * Koordinator degisince eski satir kapanir, yenisi acilir; gecmis silinmez.
 * Bir tipin ayni anda tek gecerli koordinatoru olur: `open_guard` gecerli
 * satirda tip degerini tasir, kapali satirda bostur ve benzersizdir
 * (uk_project_type_coordinators_open; B25 personel atama gecmisi deseni).
 * Personel silinemez (RESTRICT). Veri tasima yoktur; GES -> Ertugrul Sahin
 * atamasini ProjectManagers20261008Seeder yapar.
 *
 * On kosul: B00 (personnel), B48 (proje tipleri).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const SCOPE_TYPES = ['ges', 'res', 'tm', 'hes', 'bes', 'enh_eih'];

    public function up(): void
    {
        if (Schema::hasTable('project_type_coordinators')) {
            return;
        }

        Schema::create('project_type_coordinators', function (Blueprint $table): void {
            $table->id();
            $this->status($table, 'scope_type');
            $table->unsignedBigInteger('personnel_id');
            $this->ts($table, 'valid_from')->useCurrent();
            $this->ts($table, 'valid_until')->nullable();

            if ($this->isMySql()) {
                $table->string('open_guard', 32)
                    ->storedAs('CASE WHEN `valid_until` IS NULL THEN `scope_type` END')
                    ->nullable();
            } else {
                $table->string('open_guard', 32)->nullable();
            }

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('open_guard', 'uk_project_type_coordinators_open');
            $table->index(['personnel_id', 'valid_until'], 'ix_project_type_coordinators_personnel');
            $table->index(['scope_type', 'valid_from'], 'ix_project_type_coordinators_type');
            $table->foreign('personnel_id', 'fk_project_type_coordinators_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_type_coordinators', 'scope_type', self::SCOPE_TYPES);
        $this->check('project_type_coordinators', 'ck_project_type_coordinators_valid_range', '`valid_until` IS NULL OR `valid_until` >= `valid_from`');
        $this->personnelForeignKeys('project_type_coordinators');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasTable('project_type_coordinators')) {
            return;
        }

        Schema::table('project_type_coordinators', function (Blueprint $blueprint): void {
            $blueprint->dropForeign('fk_project_type_coordinators_personnel');

            foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                $blueprint->dropForeign($this->fkName('project_type_coordinators', $column));
            }
        });

        Schema::dropIfExists('project_type_coordinators');
    }
};
