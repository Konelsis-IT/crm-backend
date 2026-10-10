<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B48 - Projenin kisa adi ve proje tipleri (D-174, 8 Ekim 2026 kullanici
 * talimati: "Proje model'ine yeni alan eklenecek kisa isim diye ... diger
 * proje adi kismi aslinda Lisans adi olarak guncellenecek"; "Proje tip secim
 * mekanizmasi da yine eklenmeli ... projenin isterleri farklidir, birbirine
 * isterler karismadan ortak mantikta benzerlik tasidigi sekilde").
 *
 * Kisa ad: `projects.short_name` (bos olabilir, VARCHAR(120)). Var olan
 * `projects.name` kolonu degismez; ekranda "Lisans adi" olarak gorunur.
 * Kisa ad bossa proje her yerde lisans adiyla gorunur.
 *
 * Proje tipleri: `project_scopes` projenin KENDI kapsam satirlaridir (teklif
 * kapsamindan ayri; teklif isterleri projeye karismaz). Ayni projede bir tip
 * yalniz bir kez bulunur (uk_project_scopes_type); tipler potansiyel is /
 * teklifle ayni katalogdur (CHECK: ges, res, tm, hes, bes, enh_eih). Tip
 * basina projenin olculeri: GES MWp (`capacity_mwp`), RES / HES kurulu guc
 * MW (`capacity_mw`), BESS MWe / MWh (`power_mwe`, `energy_mwh`), ENH/EIH km
 * (`length_km`), TM fider / HES-RES turbin sayisi (`unit_count`); her tipte
 * sozlesme tutari (`contract_amount`) ve butce (`budget_amount`), projenin
 * para biriminde. Tekliften donusen projede satirlar kabul edilen teklif
 * surumunun kapsamindan kopyalanir; kaynak satir `source_proposal_version_scope_id`
 * ile izlenir (soy bagi; teklif satiri silinirse bag bosalir). Proje satiri
 * olan proje silinemez (RESTRICT). Veri tasima yoktur: var olan projeler
 * tipsiz kalir, kullanici proje duzenleme ekraninda secer.
 *
 * On kosul: B17 (projects), B43 (proposal_version_scopes).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const SCOPE_TYPES = ['ges', 'res', 'tm', 'hes', 'bes', 'enh_eih'];

    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'short_name')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->string('short_name', 120)->nullable()->after('name');
            });
        }

        if (Schema::hasTable('project_scopes')) {
            return;
        }

        Schema::create('project_scopes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $this->status($table, 'scope_type');
            $table->decimal('capacity_mwp', 12, 3)->nullable();
            $table->decimal('capacity_mw', 12, 3)->nullable();
            $table->decimal('power_mwe', 12, 3)->nullable();
            $table->decimal('energy_mwh', 12, 3)->nullable();
            $table->decimal('length_km', 12, 3)->nullable();
            $table->unsignedSmallInteger('unit_count')->nullable();
            $table->decimal('contract_amount', 18, 2)->nullable();
            $table->decimal('budget_amount', 18, 2)->nullable();
            $table->unsignedBigInteger('source_proposal_version_scope_id')->nullable();
            $table->string('note', 255)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['project_id', 'scope_type'], 'uk_project_scopes_type');
            $table->foreign('project_id', 'fk_project_scopes_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('source_proposal_version_scope_id', 'fk_project_scopes_source_scope')
                ->references('id')->on('proposal_version_scopes')->nullOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('project_scopes', 'scope_type', self::SCOPE_TYPES);
        $this->personnelForeignKeys('project_scopes');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (Schema::hasTable('project_scopes')) {
            Schema::table('project_scopes', function (Blueprint $blueprint): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                    $blueprint->dropForeign($this->fkName('project_scopes', $column));
                }
            });

            Schema::dropIfExists('project_scopes');
        }

        if (Schema::hasColumn('projects', 'short_name')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->dropColumn('short_name');
            });
        }
    }
};
