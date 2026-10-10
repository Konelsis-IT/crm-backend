<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B50 - Referanslar ve "Otomasyon / Process" proje tipi (D-177, 8 Ekim 2026
 * kullanici talimati: "Excel'de tum referanslarimizi zaten ekledik ... referans
 * eklemek icin yeni Referans resource'u olustur; proje tipi de secilebilmeli"
 * ve "Otomasyon / Process ... Toplam Maliyet - Toplam Satis seklinde 2 input").
 *
 * 1. Proje tipi katalogu (ProjectScopeType) `automation` degerini alir. Tipi
 *    listeleyen butun CHECK kisitlari yeniden yazilir (once eskisi kaldirilir,
 *    sonra yeni listeyle eklenir; MySQL 8.4 CHECK degistiremez):
 *    business_case_scopes, proposal_version_scopes, project_scopes (B48),
 *    project_type_coordinators (B49) `scope_type` ve party_activity_areas (B33)
 *    `project_type`. Tablo yoksa (grup henuz uygulanmadiysa) atlanir; o grup
 *    sonradan uygulanirsa eski listeyle kurulur ve B50 yeniden calismaz, bu
 *    yuzden grup sirasi korunur (B48, B49 -> B50). Otomasyon tipinin kendi
 *    kolonu yoktur: teklifte toplam maliyet / toplam satis (`total_cost`,
 *    `total_sales`), projede sozlesme tutari / butce kullanilir.
 * 2. `project_references`: sirketin referans listesi (yapilmis isler). Bir
 *    satir bir referans metnidir (`title`, Excel'deki metin aynen), `sort_order`
 *    listelerin ve Excel ciktisinin sirasidir. Silinmez, arsive alinir: yalniz
 *    `archived_at` (D-156). Iz kolonlari ve row_version.
 * 3. `project_reference_scope_types`: referans <-> proje tipi (bir referans
 *    birden fazla tipte olabilir, or. TM + ENH/EIH ya da GES + BESS). Ayni
 *    referansta bir tip bir kez (uk_project_reference_scope_types_type);
 *    referans silinemez (RESTRICT). Satirlar referans formundaki coklu
 *    secimle eklenir / kaldirilir (alt detay satiri).
 *
 * Veri: References20261008Seeder (DEPLOY_SEEDERS) bes Excel dosyasindaki 429
 * referansi yazar. On kosul: B29, B33, B43, B48, B49.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> B50 oncesi tip listesi (down icin). */
    private const OLD_SCOPE_TYPES = ['ges', 'res', 'tm', 'hes', 'bes', 'enh_eih'];

    /** @var list<string> */
    private const SCOPE_TYPES = ['ges', 'res', 'tm', 'hes', 'bes', 'enh_eih', 'automation'];

    /** @var list<array{0: string, 1: string}> Tipi listeleyen tablo ve kolon. */
    private const TYPED_COLUMNS = [
        ['business_case_scopes', 'scope_type'],
        ['proposal_version_scopes', 'scope_type'],
        ['project_scopes', 'scope_type'],
        ['project_type_coordinators', 'scope_type'],
        ['party_activity_areas', 'project_type'],
    ];

    public function up(): void
    {
        foreach (self::TYPED_COLUMNS as [$table, $column]) {
            if (Schema::hasTable($table)) {
                $this->replaceEnumCheck($table, $column, self::SCOPE_TYPES);
            }
        }

        if (! Schema::hasTable('project_references')) {
            Schema::create('project_references', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 500);
                $table->unsignedInteger('sort_order')->default(0);
                $this->ts($table, 'archived_at')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->index('sort_order', 'ix_project_references_sort');
            });
            $this->personnelForeignKeys('project_references');
        }

        if (! Schema::hasTable('project_reference_scope_types')) {
            Schema::create('project_reference_scope_types', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_reference_id');
                $this->status($table, 'scope_type');
                $this->auditCreated($table);

                $table->unique(['project_reference_id', 'scope_type'], 'uk_project_reference_scope_types_type');
                $table->index('scope_type', 'ix_project_reference_scope_types_type');
                $table->foreign('project_reference_id', 'fk_project_reference_scope_types_reference')
                    ->references('id')->on('project_references')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->enumCheck('project_reference_scope_types', 'scope_type', self::SCOPE_TYPES);
            $this->personnelForeignKeys('project_reference_scope_types');
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (Schema::hasTable('project_reference_scope_types')) {
            Schema::table('project_reference_scope_types', function (Blueprint $blueprint): void {
                $blueprint->dropForeign('fk_project_reference_scope_types_reference');
                $blueprint->dropForeign($this->fkName('project_reference_scope_types', 'created_by_personnel_id'));
            });

            Schema::dropIfExists('project_reference_scope_types');
        }

        if (Schema::hasTable('project_references')) {
            Schema::table('project_references', function (Blueprint $blueprint): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                    $blueprint->dropForeign($this->fkName('project_references', $column));
                }
            });

            Schema::dropIfExists('project_references');
        }

        // Otomasyon satiri varsa eski kisit eklenemez; DBA once o satirlari ele alir.
        foreach (self::TYPED_COLUMNS as [$table, $column]) {
            if (Schema::hasTable($table)) {
                $this->replaceEnumCheck($table, $column, self::OLD_SCOPE_TYPES);
            }
        }
    }

    /**
     * Var olan tip CHECK'ini kaldirip yeni listeyle ekler (yalniz MySQL; SQLite
     * sonradan kisit eklemez). Kisit yoksa yalniz eklenir.
     *
     * @param  list<string>  $values
     */
    private function replaceEnumCheck(string $table, string $column, array $values): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $name = $this->shorten("ck_{$table}_{$column}_enum");
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'CHECK'],
        );

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $name));
        }

        $this->enumCheck($table, $column, $values);
    }
};
