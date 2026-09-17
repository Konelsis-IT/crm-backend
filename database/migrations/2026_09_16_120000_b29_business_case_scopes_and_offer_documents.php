<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B29 - Is dosyasi teklif tipi, proje kapsamlari, teklif durumu ve teklif
 * belgeleri (D-101, 16 Eylul 2026 kullanici talimati).
 *
 * Teklif tipi: `business_cases.offer_type` is dosyasinin butcesel mi kat'i
 * mi teklif edildigini soyler (enumCheck: budgetary | firm). Ekranda
 * "Kritiklik" secimi yerine bu alan gorunur; `criticality` kolonu ve verisi
 * dokunulmadan durur, yalniz ekranlardan kalkar. Bos ise tip secilmemistir.
 *
 * Proje kapsamlari: `business_case_scopes` is dosyasinda isaretlenen her
 * proje tipi (GES / RES / TM / HES / BES / ENH-EIH) icin bir satirdir;
 * ayni is dosyasinda bir tip yalniz bir kez bulunur (uk_business_case_
 * scopes_type). Tip basina kendi tutar alanlari vardir: GES icin kurulu guc
 * (MW), maliyet, satis ve MW basi maliyet/satis; RES icin Respark malzeme,
 * insaat ve montaj tutarlari; TM icin toplam maliyet, toplam satis ve fider
 * basi maliyet. HES / BES / ENH-EIH alanlari kullanici bildirince eklenir;
 * bu tipler simdilik yalniz secim satiri tasir. Yuklenen kapsam listesi
 * (Excel) Dokumanlar'da `KPS` turunde bir belge olur ve `scope_document_id`
 * ile baglanir; belge silinirse baglanti bosalir (nullOnDelete), kapsam
 * satiri olan is dosyasi silinemez (RESTRICT). Tutarlar DECIMAL(18,2), guc
 * DECIMAL(12,3); para birimi is dosyasinin para birimidir.
 *
 * Teklif durumu: `proposals.offer_status` teklifin ticari durumunu
 * (Verilecek teklif / Verilen teklif / Onaylandi / Kacan firsat) tasir
 * (enumCheck: to_be_submitted | submitted | approved | lost). Ic is akisi
 * kolonu `status` oldugu gibi kalir; ikisi ayri kavramdir.
 *
 * Teklif belgeleri: `proposal_documents.document_role` CHECK'i bes yeni
 * rolle genisletilir: customer_expectations (firmanin beklentileri),
 * proposal_letter (teklif mektubu), references (referanslar belgesi),
 * catalog (genel katalog) ve scope_list (kapsam listesi). MySQL CHECK
 * degistirilemedigi icin eski kisit dusurulup eski + yeni degerlerle yeniden
 * kurulur; down() eski listeyi geri koyar.
 *
 * Uygulama sonrasi `DocumentTypeSeeder` calistirilir (KPS / BEK / TKM / REF
 * / KAT dokuman turleri).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> Eski (B16) teklif belgesi rolleri. */
    private const ORIGINAL_DOCUMENT_ROLES = [
        'technical_offer', 'commercial_offer', 'spec_compliance', 'brand_list', 'responsibility_matrix',
        'schedule', 'site_survey', 'supplier_quote', 'kmz', 'photo', 'other',
    ];

    /** @var list<string> B29 ile gelen teklif belgesi rolleri. */
    private const ADDED_DOCUMENT_ROLES = [
        'customer_expectations', 'proposal_letter', 'references', 'catalog', 'scope_list',
    ];

    public function up(): void
    {
        Schema::table('business_cases', function (Blueprint $table): void {
            $this->status($table, 'offer_type')->nullable()->after('criticality');
        });
        $this->enumCheck('business_cases', 'offer_type', ['budgetary', 'firm']);

        Schema::create('business_case_scopes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $this->status($table, 'scope_type');
            $table->decimal('capacity_mw', 12, 3)->nullable();
            $table->decimal('cost_amount', 18, 2)->nullable();
            $table->decimal('sales_amount', 18, 2)->nullable();
            $table->decimal('cost_per_mw', 18, 2)->nullable();
            $table->decimal('sales_per_mw', 18, 2)->nullable();
            $table->decimal('res_material_amount', 18, 2)->nullable();
            $table->decimal('res_construction_amount', 18, 2)->nullable();
            $table->decimal('res_assembly_amount', 18, 2)->nullable();
            $table->decimal('tm_total_cost', 18, 2)->nullable();
            $table->decimal('tm_total_sales', 18, 2)->nullable();
            $table->decimal('tm_feeder_cost', 18, 2)->nullable();
            $table->unsignedBigInteger('scope_document_id')->nullable();
            $table->string('note', 255)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['business_case_id', 'scope_type'], 'uk_business_case_scopes_type');
            $table->foreign('business_case_id', 'fk_business_case_scopes_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('scope_document_id', 'fk_business_case_scopes_document')
                ->references('id')->on('documents')->nullOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_case_scopes', 'scope_type', ['ges', 'res', 'tm', 'hes', 'bes', 'enh_eih']);
        $this->personnelForeignKeys('business_case_scopes');

        Schema::table('proposals', function (Blueprint $table): void {
            $this->status($table, 'offer_status')->nullable()->after('status');
        });
        $this->enumCheck('proposals', 'offer_status', ['to_be_submitted', 'submitted', 'approved', 'lost']);

        // MySQL CHECK degistirilemez: eski kisit duser, eski + yeni degerlerle yeniden kurulur.
        $this->dropCheck('proposal_documents', 'ck_proposal_documents_document_role_enum');
        $this->enumCheck('proposal_documents', 'document_role', [...self::ORIGINAL_DOCUMENT_ROLES, ...self::ADDED_DOCUMENT_ROLES]);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        // Teklif belgesi rolleri: B16'daki ozgun liste geri gelir.
        $this->dropCheck('proposal_documents', 'ck_proposal_documents_document_role_enum');
        $this->enumCheck('proposal_documents', 'document_role', self::ORIGINAL_DOCUMENT_ROLES);

        // MySQL, CHECK'in kullandigi kolonu dusurmeye izin vermez; once kisit kalkar.
        $this->dropCheck('proposals', 'ck_proposals_offer_status_enum');

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropColumn('offer_status');
        });

        Schema::table('business_case_scopes', function (Blueprint $blueprint): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id', 'archived_by_personnel_id'] as $column) {
                if (Schema::hasColumn('business_case_scopes', $column)) {
                    $blueprint->dropForeign($this->fkName('business_case_scopes', $column));
                }
            }
        });

        Schema::dropIfExists('business_case_scopes');

        $this->dropCheck('business_cases', 'ck_business_cases_offer_type_enum');

        Schema::table('business_cases', function (Blueprint $table): void {
            $table->dropColumn('offer_type');
        });
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $name));
    }
};
