<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B33 - Taraf faaliyet alanlari, koken, rakip firma ve Dernek / oda tipi
 * (D-107, 21 Eylul 2026 kullanici karari; Firma_Harita_Takip.xlsx Genel_Harita).
 *
 * Bir firmanin ne is yaptigi uc bagimsiz boyutla tutulur ve her biri listede
 * ayri suzgectir: Proje tipi (is dosyasindaki ProjectScopeType listesi) +
 * Faaliyet alani + Alt faaliyet alani. Bir firma birden fazla satir tasir
 * (ornek: Huawei hem GES inverter hem BES batarya).
 *
 *  - `activity_areas`: faaliyet alani katalogu, iki seviye. `parent_id` bos
 *    ise ana faaliyet alani, dolu ise alt faaliyet alanidir. Ayarlar'dan
 *    duzenlenir; ilk liste ActivityAreaSeeder ile gelir. Silinmez, pasife
 *    alinir.
 *  - `party_activity_areas`: taraf <-> faaliyet satiri. `project_type` bos ise
 *    "tum proje tipleri"dir. Alt faaliyet alani istege baglidir.
 *  - `parties.origin` (Koken: yerli, Avrupa, Cin, yabanci) firma duzeyindedir.
 *  - `parties.is_competitor` (Rakip firma) isaretini ilgili personel verir.
 *  - `party_roles.role_code` listesine `association` (Dernek / oda) eklenir;
 *    Dernekler menusu bu tipin listesidir.
 *
 * On kosul: B16 (parties, party_roles).
 */
return new class extends KonelsisMigration
{
    private const PROJECT_TYPES = ['ges', 'res', 'tm', 'hes', 'bes', 'enh_eih'];

    private const ROLE_CODES = [
        'customer', 'supplier', 'subcontractor', 'partner', 'employer', 'investor',
        'consultant', 'carrier', 'authority',
    ];

    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $this->status($table, 'origin')->nullable()->after('visit_priority');
            $table->boolean('is_competitor')->default(false)->after('origin');

            $table->index('origin', 'ix_parties_origin');
            $table->index('is_competitor', 'ix_parties_is_competitor');
        });
        $this->enumCheck('parties', 'origin', ['domestic', 'europe', 'china', 'foreign']);

        $this->dropCheck('party_roles', 'ck_party_roles_role_code_enum');
        $this->enumCheck('party_roles', 'role_code', [...self::ROLE_CODES, 'association']);

        Schema::create('activity_areas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $this->code($table, 'code');
            $table->string('name_tr', 150);
            $table->string('name_en', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_activity_areas_code');
            $table->index(['parent_id', 'sort_order'], 'ix_activity_areas_parent');
            $table->foreign('parent_id', 'fk_activity_areas_parent')
                ->references('id')->on('activity_areas')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('activity_areas', 'status', ['active', 'inactive']);
        $this->personnelForeignKeys('activity_areas');

        Schema::create('party_activity_areas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $this->status($table, 'project_type')->nullable();
            $table->unsignedBigInteger('activity_area_id');
            $table->unsignedBigInteger('sub_activity_area_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index('party_id', 'ix_party_activity_areas_party');
            $table->index(['project_type', 'activity_area_id'], 'ix_party_activity_areas_type_area');
            $table->index('sub_activity_area_id', 'ix_party_activity_areas_sub');
            $table->foreign('party_id', 'fk_party_activity_areas_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('activity_area_id', 'fk_party_activity_areas_area')
                ->references('id')->on('activity_areas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('sub_activity_area_id', 'fk_party_activity_areas_sub')
                ->references('id')->on('activity_areas')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('party_activity_areas', 'project_type', self::PROJECT_TYPES);
        $this->personnelForeignKeys('party_activity_areas');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach (['party_activity_areas', 'activity_areas'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                    $blueprint->dropForeign($this->fkName($table, $column));
                }
            });
        }

        Schema::dropIfExists('party_activity_areas');
        Schema::dropIfExists('activity_areas');

        // Dernek tipinde satir varsa eski liste CHECK'i kabul etmez; kisit oldugu gibi kalir.
        if (! DB::table('party_roles')->where('role_code', 'association')->exists()) {
            $this->restoreRoleCheck();
        }

        // MySQL, CHECK'in kullandigi kolonu dusurmeye izin vermez; once kisit kalkar.
        $this->dropCheck('parties', 'ck_parties_origin_enum');

        Schema::table('parties', function (Blueprint $table): void {
            $table->dropIndex('ix_parties_origin');
            $table->dropIndex('ix_parties_is_competitor');
            $table->dropColumn(['origin', 'is_competitor']);
        });
    }

    /** Dernek tipinde satir yoksa rol listesi eski haline doner. */
    private function restoreRoleCheck(): void
    {
        $this->dropCheck('party_roles', 'ck_party_roles_role_code_enum');
        $this->enumCheck('party_roles', 'role_code', self::ROLE_CODES);
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $this->shorten($name)));
    }
};
