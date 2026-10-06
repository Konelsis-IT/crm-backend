<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B43 - Ihale -> Potansiyel is -> Teklif -> Proje zinciri (D-155, 5 Ekim 2026
 * kullanici talimati).
 *
 * Ihale potansiyel isten once gelir: `tender_notices.business_case_id` bos
 * kalabilir; potansiyel is ihaleden acilinca baglanir.
 *
 * Potansiyel is (business_cases):
 * - `license_status` "Proje durumu": Lisanssiz 5.1-C / Lisanssiz 5.1-H /
 *   Onlisans / Lisans (enumCheck).
 * - `heat_score` teklif sicakligi (0-100): teklif oncesi kontrol listesinin
 *   agirlikli doluluk orani. Uygulama her kayitta yeniden hesaplar.
 * - `business_case_checklist_answers`: kontrol listesi alt maddelerinin
 *   cevaplari (evet / hayir / bilinmiyor). Liste tanimlari kodda
 *   (App\Support\Acquisition\ChecklistTemplates); satir sablon + madde
 *   koduyla tekildir.
 * - `business_case_documents`: potansiyel isin belgeleri. Kontrol listesi ana
 *   maddesine ait belge sablon + madde kodu tasir, genel belgede ikisi bostur.
 *   Yeni yukleme ayni belgenin yeni revizyonudur; satir silinmez.
 *
 * Teklif kapsamlari: `proposal_version_scopes` proje kapsaminin tutarlarini
 * teklif surumune baglar (kapsam potansiyel isten teklife tasindi). Tip basina
 * miktar (GES MWp, BESS MWe / MWh, ENH km), birim maliyet / satis, toplam
 * maliyet / satis, RES kalemleri ve kapsam listesi belgesi (surumun baktigi
 * revizyonla). Ayni surumde bir tip yalniz bir kez bulunur. Mevcut
 * `business_case_scopes` tutarlari isin secili (yoksa en son) teklifinin
 * guncel surumune kopyalanir (kullanici karari); eski satirlar proje tipi
 * secimi olarak kalir.
 *
 * Taslak: `is_draft` + `draft_step` (kaldigi adim) business_cases,
 * proposals ve tender_notices tablolarinda.
 *
 * Teklif belgesi rolleri: `deviation_list` (Deviasyon listesi) eklenir.
 *
 * On kosul: B16 (is alim), B29 (kapsamlar), B06 (dokumanlar).
 * Uygulama sonrasi: DocumentTypeSeeder (PIB / SUY / DEV / MRK / SRM) ve
 * FeatureSeeder.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> B29 sonundaki teklif belgesi rolleri. */
    private const PREVIOUS_DOCUMENT_ROLES = [
        'technical_offer', 'commercial_offer', 'spec_compliance', 'brand_list', 'responsibility_matrix',
        'schedule', 'site_survey', 'supplier_quote', 'kmz', 'photo', 'other',
        'customer_expectations', 'proposal_letter', 'references', 'catalog', 'scope_list',
    ];

    /** @var list<string> */
    private const SCOPE_TYPES = ['ges', 'res', 'tm', 'hes', 'bes', 'enh_eih'];

    public function up(): void
    {
        Schema::table('tender_notices', function (Blueprint $table): void {
            $table->unsignedBigInteger('business_case_id')->nullable()->change();
            $table->boolean('is_draft')->default(false)->after('status');
            $this->code($table, 'draft_step', 16)->nullable()->after('is_draft');
        });

        Schema::table('business_cases', function (Blueprint $table): void {
            $this->status($table, 'license_status')->nullable()->after('offer_type');
            $table->unsignedTinyInteger('heat_score')->nullable()->after('license_status');
            $table->boolean('is_draft')->default(false)->after('heat_score');
            $this->code($table, 'draft_step', 16)->nullable()->after('is_draft');
        });
        $this->enumCheck('business_cases', 'license_status', ['unlicensed_5_1_c', 'unlicensed_5_1_h', 'pre_license', 'license']);
        $this->check('business_cases', 'ck_business_cases_heat_score_range', '`heat_score` IS NULL OR `heat_score` <= 100');

        Schema::table('proposals', function (Blueprint $table): void {
            $table->boolean('is_draft')->default(false)->after('offer_status');
            $this->code($table, 'draft_step', 16)->nullable()->after('is_draft');
        });

        Schema::create('business_case_checklist_answers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $this->code($table, 'template_code', 16);
            $this->code($table, 'item_code', 16);
            $this->status($table, 'answer');
            $table->string('note', 255)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['business_case_id', 'template_code', 'item_code'], 'uk_bc_checklist_answers_item');
            $table->foreign('business_case_id', 'fk_bc_checklist_answers_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_case_checklist_answers', 'answer', ['yes', 'no', 'unknown']);
        $this->personnelForeignKeys('business_case_checklist_answers');

        Schema::create('business_case_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $table->unsignedBigInteger('document_id');
            $this->code($table, 'template_code', 16)->nullable();
            $this->code($table, 'item_code', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['business_case_id', 'document_id'], 'uk_business_case_documents_document');
            $table->index(['business_case_id', 'template_code', 'item_code'], 'ix_business_case_documents_item');
            $table->foreign('business_case_id', 'fk_business_case_documents_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_id', 'fk_business_case_documents_document')
                ->references('id')->on('documents')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('business_case_documents');

        Schema::create('proposal_version_scopes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_id');
            $this->status($table, 'scope_type');
            $table->decimal('capacity_mwp', 12, 3)->nullable();
            $table->decimal('power_mwe', 12, 3)->nullable();
            $table->decimal('energy_mwh', 12, 3)->nullable();
            $table->decimal('length_km', 12, 3)->nullable();
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->decimal('unit_sales', 18, 2)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->decimal('total_sales', 18, 2)->nullable();
            $table->decimal('res_material_amount', 18, 2)->nullable();
            $table->decimal('res_construction_amount', 18, 2)->nullable();
            $table->decimal('res_assembly_amount', 18, 2)->nullable();
            $table->unsignedBigInteger('scope_document_id')->nullable();
            $table->unsignedBigInteger('scope_document_revision_id')->nullable();
            $table->string('note', 255)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['proposal_version_id', 'scope_type'], 'uk_proposal_version_scopes_type');
            $table->foreign('proposal_version_id', 'fk_proposal_version_scopes_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('scope_document_id', 'fk_proposal_version_scopes_document')
                ->references('id')->on('documents')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('scope_document_revision_id', 'fk_proposal_version_scopes_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('proposal_version_scopes', 'scope_type', self::SCOPE_TYPES);
        $this->personnelForeignKeys('proposal_version_scopes');

        // MySQL CHECK degistirilemez: eski kisit duser, deviation_list eklenerek yeniden kurulur.
        $this->dropCheck('proposal_documents', 'ck_proposal_documents_document_role_enum');
        $this->enumCheck('proposal_documents', 'document_role', [...self::PREVIOUS_DOCUMENT_ROLES, 'deviation_list']);

        $this->copyCaseScopesToProposals();
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        $this->dropCheck('proposal_documents', 'ck_proposal_documents_document_role_enum');
        $this->enumCheck('proposal_documents', 'document_role', self::PREVIOUS_DOCUMENT_ROLES);

        foreach (['proposal_version_scopes', 'business_case_documents', 'business_case_checklist_answers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                    $blueprint->dropForeign($this->fkName($table, $column));
                }
            });
            Schema::dropIfExists($table);
        }

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropColumn(['is_draft', 'draft_step']);
        });

        $this->dropCheck('business_cases', 'ck_business_cases_license_status_enum');
        $this->dropCheck('business_cases', 'ck_business_cases_heat_score_range');

        Schema::table('business_cases', function (Blueprint $table): void {
            $table->dropColumn(['license_status', 'heat_score', 'is_draft', 'draft_step']);
        });

        Schema::table('tender_notices', function (Blueprint $table): void {
            $table->dropColumn(['is_draft', 'draft_step']);
        });
        // business_case_id NOT NULL'a geri donmez: bos satirlar olabilir.
    }

    /**
     * Potansiyel isteki kapsam tutarlarini isin secili (yoksa en son acilan)
     * teklifinin guncel (yoksa en yeni) surumune kopyalar. Ayni surumde ayni
     * tip zaten varsa dokunulmaz; teklifi olmayan isler atlanir (tutarlar ilk
     * teklif formunda dolu gelir).
     */
    private function copyCaseScopesToProposals(): void
    {
        $now = Carbon::now('UTC');
        $scopes = DB::table('business_case_scopes')->orderBy('id')->get();

        foreach ($scopes as $scope) {
            $proposal = DB::table('proposals')
                ->where('business_case_id', $scope->business_case_id)
                ->orderByDesc('is_selected')
                ->orderByDesc('id')
                ->first();

            if ($proposal === null) {
                continue;
            }

            $versionId = $proposal->current_version_id ?? DB::table('proposal_versions')
                ->where('proposal_id', $proposal->id)
                ->orderByDesc('version_no')
                ->value('id');

            if ($versionId === null) {
                continue;
            }

            $exists = DB::table('proposal_version_scopes')
                ->where('proposal_version_id', $versionId)
                ->where('scope_type', $scope->scope_type)
                ->exists();

            if ($exists) {
                continue;
            }

            $revisionId = $scope->scope_document_id === null ? null : DB::table('document_revisions')
                ->where('document_id', $scope->scope_document_id)
                ->orderByDesc('revision_no')
                ->value('id');

            DB::table('proposal_version_scopes')->insert([
                'proposal_version_id' => $versionId,
                'scope_type' => $scope->scope_type,
                ...$this->mappedAmounts($scope),
                'scope_document_id' => $scope->scope_document_id,
                'scope_document_revision_id' => $revisionId,
                'note' => $scope->note,
                'created_at' => $now,
                'created_by_personnel_id' => $scope->created_by_personnel_id,
                'updated_at' => $now,
                'updated_by_personnel_id' => $scope->updated_by_personnel_id ?? $scope->created_by_personnel_id,
                'row_version' => 1,
            ]);
        }
    }

    /**
     * B29 kolonlarindan yeni kolonlara: GES kurulu guc -> MWp, MW basi -> birim;
     * TM toplamlari ve fider basi; HES maliyet / satis ve birim; RES kalemleri.
     *
     * @return array<string, mixed>
     */
    private function mappedAmounts(object $scope): array
    {
        $hesUnit = property_exists($scope, 'hes_unit_cost') ? $scope->hes_unit_cost : null;

        return match ($scope->scope_type) {
            'ges' => [
                'capacity_mwp' => $scope->capacity_mw,
                'unit_cost' => $scope->cost_per_mw,
                'unit_sales' => $scope->sales_per_mw,
                'total_cost' => $scope->cost_amount,
                'total_sales' => $scope->sales_amount,
            ],
            'tm' => [
                'unit_cost' => $scope->tm_feeder_cost,
                'total_cost' => $scope->tm_total_cost,
                'total_sales' => $scope->tm_total_sales,
            ],
            'hes' => [
                'unit_cost' => $hesUnit,
                'total_cost' => $scope->cost_amount,
                'total_sales' => $scope->sales_amount,
            ],
            'res' => [
                'res_material_amount' => $scope->res_material_amount,
                'res_construction_amount' => $scope->res_construction_amount,
                'res_assembly_amount' => $scope->res_assembly_amount,
            ],
            default => [],
        };
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $name));
    }
};
