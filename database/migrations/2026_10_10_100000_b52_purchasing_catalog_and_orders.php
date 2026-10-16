<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B52 - Satin alma: katalog, teklif maliyet kalemleri, siparis kalemleri,
 * tedarikci teklifleri ve sirket ici alimlar (D-189, 10 Ekim 2026 kullanici
 * talimati: "urunler - hizmetler - genel giderler - idari kadro yapisi gibi
 * kavramlar sisteme sabit olarak girilmeli ... Excel ile iceri aktarimi
 * sagladigimiz maliyet kalemleri sistemi icin satin alma tarafinda o kismi
 * eklemeliyiz. Ayrica bir Teklif araciligi ile geldigi bilinmelidir ... proje
 * onay vermeden satin alima dusmeyecek ... Siparisi gecilecek urunlerin
 * teklifleri birden fazla olabilir ... Satin alma departmani en uygun teklifi
 * belirleyecektir").
 *
 * 1. `catalog_categories`: sabit katalogun kategorileri; tur (kind) basina
 *    agac (parent_id). Turler: product Urun, service Hizmet, general_expense
 *    Genel gider, admin_staff Idari kadro, office Sirket ici ihtiyac.
 * 2. `catalog_items`: sabit katalog kalemi. Ayni turde ayni normalize ad bir
 *    kez (uk_catalog_items_kind_name): Excel her yuklendiginde yeni kayit
 *    acilmaz, var olana baglanir (D-187 kullanicinin en buyuk kurali).
 *    `requires_approval` kapaliysa siparis onaysiz tamamlanir;
 *    `renewal_months` yenilenen urun / hizmet (sunucu, abonelik, kirtasiye).
 * 3. `catalog_item_aliases`: Excel'deki farkli yazimlarin esleme hafizasi;
 *    bir normalize yazim tek kaleme baglidir.
 * 4. `cost_lists`: teklif surumune yuklenmis maliyet Excel'inin (B51 MLY
 *    belgesi) aktarimi; ayni surumde ayni revizyon bir kez aktarilir.
 * 5. `cost_list_lines`: Excel satiri (Urun/Hizmet, Idari kadro, Genel
 *    giderler) + katalog eslesmesi (matched / new / similar) + fiyat durumu
 *    (Excel satir rengi: yesil firm = fiyati kesin, sari open = netlesmedi).
 * 6. `purchase_needs`: siparisi gecilecek kalem. Kaynak: teklif maliyet
 *    kalemi (once proje onayi, sonra satin alma), departman talebi (sirket
 *    ici) ya da satin almanin elle actigi kalem. Bir maliyet satiri bir kez
 *    satin almaya gider (uk_purchase_needs_cost_line). Onay motoru (B07)
 *    `approval_request_id` ile baglanir; siparis onaysiz tamamlanamaz.
 * 7. `purchase_quotes`: kalem icin tedarikci teklifleri (birden fazla);
 *    onerilen teklif `purchase_needs.recommended_quote_id`. Tedarikci taraf
 *    (party) ya da serbest ad. Teklif kalemin alt satiridir (silinebilir).
 * 8. `company_purchases`: sirket ici alinmis urun / hizmetler (bilgisayar,
 *    yazilim, sunucu, kirtasiye); yenileme donemi ve sonraki yenileme tarihi
 *    ile gelecek giderler hesaplanir.
 *
 * Silme yok: katalog, kalem, siparis kalemi ve sirket ici alim arsive alinir
 * (yalniz archived_at, D-156). Veri: Catalog20261010Seeder (DEPLOY_SEEDERS)
 * katalogu Kartal RES Hibrit GES maliyet Excel'inden kurar;
 * PurchaseApprovalPolicy20261010Seeder 4.000 TL siniri ile iki onay
 * politikasini ekler. On kosul: B06, B07, B16, B43, B51.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const KINDS = ['product', 'service', 'general_expense', 'admin_staff', 'office'];

    /** @var list<string> */
    private const LINE_TYPES = ['product_service', 'admin_staff', 'general_expense'];

    /** @var list<string> */
    private const MATCH_STATUSES = ['matched', 'new', 'similar'];

    /** @var list<string> */
    private const PRICE_STATES = ['firm', 'open'];

    /** @var list<string> */
    private const NEED_SOURCES = ['proposal_cost', 'office_request', 'manual'];

    /** @var list<string> */
    private const NEED_STATUSES = ['awaiting_project', 'open', 'quoting', 'recommended', 'awaiting_approval', 'approved', 'ordered', 'completed', 'cancelled'];

    public function up(): void
    {
        $this->createCatalog();
        $this->createCostLists();
        $this->createPurchaseNeeds();
        $this->createCompanyPurchases();
    }

    private function createCatalog(): void
    {
        if (! Schema::hasTable('catalog_categories')) {
            Schema::create('catalog_categories', function (Blueprint $table): void {
                $table->id();
                $this->status($table, 'kind');
                $table->unsignedBigInteger('parent_id')->nullable();
                $this->code($table, 'code', 64);
                $table->string('name', 200);
                $table->unsignedInteger('sort_order')->default(0);
                $this->ts($table, 'archived_at')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->unique('code', 'uk_catalog_categories_code');
                $table->index(['kind', 'parent_id', 'sort_order'], 'ix_catalog_categories_kind_parent');
                $table->foreign('parent_id', 'fk_catalog_categories_parent')
                    ->references('id')->on('catalog_categories')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->enumCheck('catalog_categories', 'kind', self::KINDS);
            $this->personnelForeignKeys('catalog_categories');
        }

        if (! Schema::hasTable('catalog_items')) {
            Schema::create('catalog_items', function (Blueprint $table): void {
                $table->id();
                $this->status($table, 'kind');
                $table->unsignedBigInteger('catalog_category_id')->nullable();
                $this->code($table, 'code', 64);
                $table->string('name', 300);
                $table->string('normalized_name', 300);
                $table->string('unit', 40)->nullable();
                $this->asciiChar($table, 'currency_code', 3)->nullable();
                $table->string('brand', 120)->nullable();
                $table->text('specification')->nullable();
                $table->boolean('requires_approval')->default(true);
                $table->unsignedSmallInteger('renewal_months')->nullable();
                $this->ts($table, 'archived_at')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->unique('code', 'uk_catalog_items_code');
                $table->unique(['kind', 'normalized_name'], 'uk_catalog_items_kind_name');
                $table->index('catalog_category_id', 'ix_catalog_items_category');
                $table->index('normalized_name', 'ix_catalog_items_normalized_name');
                $table->foreign('catalog_category_id', 'fk_catalog_items_category')
                    ->references('id')->on('catalog_categories')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('currency_code', 'fk_catalog_items_currency')
                    ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->enumCheck('catalog_items', 'kind', self::KINDS);
            $this->check('catalog_items', 'ck_catalog_items_renewal', '`renewal_months` IS NULL OR `renewal_months` > 0');
            $this->personnelForeignKeys('catalog_items');
        }

        if (! Schema::hasTable('catalog_item_aliases')) {
            Schema::create('catalog_item_aliases', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('catalog_item_id');
                $table->string('alias', 300);
                $table->string('normalized_alias', 300);
                $this->auditCreated($table);

                $table->unique('normalized_alias', 'uk_catalog_item_aliases_alias');
                $table->index('catalog_item_id', 'ix_catalog_item_aliases_item');
                $table->foreign('catalog_item_id', 'fk_catalog_item_aliases_item')
                    ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys('catalog_item_aliases');
        }
    }

    private function createCostLists(): void
    {
        if (! Schema::hasTable('cost_lists')) {
            Schema::create('cost_lists', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('proposal_id');
                $table->unsignedBigInteger('proposal_version_id');
                $table->unsignedBigInteger('proposal_version_scope_id')->nullable();
                $table->unsignedBigInteger('document_revision_id');
                $table->string('source_file_name', 255);
                $this->asciiChar($table, 'currency_code', 3)->nullable();
                $table->decimal('total_cost', 20, 4)->default(0);
                $table->decimal('total_sale', 20, 4)->nullable();
                $table->unsignedInteger('line_count')->default(0);
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->unique(['proposal_version_id', 'document_revision_id'], 'uk_cost_lists_version_revision');
                $table->index('proposal_id', 'ix_cost_lists_proposal');
                $table->foreign('proposal_id', 'fk_cost_lists_proposal')
                    ->references('id')->on('proposals')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('proposal_version_id', 'fk_cost_lists_version')
                    ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('proposal_version_scope_id', 'fk_cost_lists_scope')
                    ->references('id')->on('proposal_version_scopes')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('document_revision_id', 'fk_cost_lists_revision')
                    ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('currency_code', 'fk_cost_lists_currency')
                    ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys('cost_lists');
        }

        if (! Schema::hasTable('cost_list_lines')) {
            Schema::create('cost_list_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('cost_list_id');
                $this->status($table, 'line_type');
                $table->string('excel_sheet', 100)->nullable();
                $table->unsignedInteger('excel_row')->nullable();
                $table->string('section_no', 20)->nullable();
                $table->string('section_name', 200)->nullable();
                $table->string('group_name', 200)->nullable();
                $table->string('line_no', 20)->nullable();
                $table->string('description', 500);
                $table->unsignedBigInteger('catalog_item_id')->nullable();
                $this->status($table, 'match_status');
                $table->unsignedBigInteger('suggested_catalog_item_id')->nullable();
                $table->unsignedTinyInteger('match_score')->nullable();
                $table->decimal('quantity', 20, 4)->nullable();
                $table->string('unit', 40)->nullable();
                $table->decimal('duration', 20, 4)->nullable();
                $table->decimal('unit_cost', 20, 6)->nullable();
                $this->asciiChar($table, 'currency_code', 3)->nullable();
                $table->decimal('total_cost', 20, 4)->default(0);
                $table->decimal('total_sale', 20, 4)->nullable();
                $this->status($table, 'price_state')->nullable();
                $table->text('note')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->index(['cost_list_id', 'line_type', 'sort_order'], 'ix_cost_list_lines_list_type');
                $table->index('catalog_item_id', 'ix_cost_list_lines_item');
                $table->index('suggested_catalog_item_id', 'ix_cost_list_lines_suggested');
                $table->foreign('cost_list_id', 'fk_cost_list_lines_list')
                    ->references('id')->on('cost_lists')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('catalog_item_id', 'fk_cost_list_lines_item')
                    ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('suggested_catalog_item_id', 'fk_cost_list_lines_suggested')
                    ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('currency_code', 'fk_cost_list_lines_currency')
                    ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->enumCheck('cost_list_lines', 'line_type', self::LINE_TYPES);
            $this->enumCheck('cost_list_lines', 'match_status', self::MATCH_STATUSES);
            $this->check('cost_list_lines', 'ck_cost_list_lines_price_state_enum', "`price_state` IS NULL OR `price_state` IN ('".implode("', '", self::PRICE_STATES)."')");
            $this->personnelForeignKeys('cost_list_lines');
        }
    }

    private function createPurchaseNeeds(): void
    {
        if (! Schema::hasTable('purchase_needs')) {
            Schema::create('purchase_needs', function (Blueprint $table): void {
                $table->id();
                $this->status($table, 'source');
                $table->unsignedBigInteger('catalog_item_id');
                $table->unsignedBigInteger('cost_list_line_id')->nullable();
                $table->unsignedBigInteger('proposal_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('org_unit_id')->nullable();
                $table->unsignedBigInteger('requested_by_personnel_id')->nullable();
                $table->string('title', 500);
                $table->decimal('quantity', 20, 4)->default(1);
                $table->string('unit', 40)->nullable();
                $table->decimal('estimated_unit_cost', 20, 6)->nullable();
                $this->asciiChar($table, 'estimated_currency_code', 3)->nullable();
                $table->date('needed_by')->nullable();
                $table->boolean('is_urgent')->default(false);
                $this->status($table);
                $table->unsignedBigInteger('project_approved_by_personnel_id')->nullable();
                $this->ts($table, 'project_approved_at')->nullable();
                $table->unsignedBigInteger('recommended_quote_id')->nullable();
                $table->unsignedBigInteger('recommended_by_personnel_id')->nullable();
                $this->ts($table, 'recommended_at')->nullable();
                $table->unsignedBigInteger('approval_request_id')->nullable();
                $table->unsignedBigInteger('ordered_by_personnel_id')->nullable();
                $this->ts($table, 'ordered_at')->nullable();
                $table->date('expected_delivery_on')->nullable();
                $table->unsignedBigInteger('completed_by_personnel_id')->nullable();
                $this->ts($table, 'completed_at')->nullable();
                $this->ts($table, 'cancelled_at')->nullable();
                $table->text('note')->nullable();
                $this->ts($table, 'archived_at')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->unique('cost_list_line_id', 'uk_purchase_needs_cost_line');
                $table->index(['status', 'needed_by'], 'ix_purchase_needs_status_needed');
                $table->index('catalog_item_id', 'ix_purchase_needs_item');
                $table->index('proposal_id', 'ix_purchase_needs_proposal');
                $table->index('project_id', 'ix_purchase_needs_project');
                $table->index('org_unit_id', 'ix_purchase_needs_org_unit');
                $table->foreign('catalog_item_id', 'fk_purchase_needs_item')
                    ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('cost_list_line_id', 'fk_purchase_needs_cost_line')
                    ->references('id')->on('cost_list_lines')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('proposal_id', 'fk_purchase_needs_proposal')
                    ->references('id')->on('proposals')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('project_id', 'fk_purchase_needs_project')
                    ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('org_unit_id', 'fk_purchase_needs_org_unit')
                    ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('estimated_currency_code', 'fk_purchase_needs_currency')
                    ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('approval_request_id', 'fk_purchase_needs_approval_request')
                    ->references('id')->on('approval_requests')->restrictOnDelete()->restrictOnUpdate();

                foreach (['requested_by', 'project_approved_by', 'recommended_by', 'ordered_by', 'completed_by'] as $who) {
                    $table->foreign($who.'_personnel_id', 'fk_purchase_needs_'.$who)
                        ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
                }
            });
            $this->enumCheck('purchase_needs', 'source', self::NEED_SOURCES);
            $this->enumCheck('purchase_needs', 'status', self::NEED_STATUSES);
            $this->check('purchase_needs', 'ck_purchase_needs_quantity', '`quantity` > 0');
            $this->personnelForeignKeys('purchase_needs');
        }

        if (! Schema::hasTable('purchase_quotes')) {
            Schema::create('purchase_quotes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('purchase_need_id');
                $table->unsignedBigInteger('supplier_party_id')->nullable();
                $table->string('supplier_name', 200)->nullable();
                $table->string('brand_model', 200)->nullable();
                $table->decimal('quantity', 20, 4)->default(1);
                $table->decimal('unit_price', 20, 6);
                $this->asciiChar($table, 'currency_code', 3);
                $table->decimal('try_rate', 20, 6)->nullable();
                $table->decimal('total_amount', 20, 4);
                $table->unsignedSmallInteger('lead_time_days')->nullable();
                $table->date('delivery_on')->nullable();
                $table->string('payment_terms', 255)->nullable();
                $table->date('valid_until')->nullable();
                $table->text('note')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->index('purchase_need_id', 'ix_purchase_quotes_need');
                $table->index('supplier_party_id', 'ix_purchase_quotes_supplier');
                $table->foreign('purchase_need_id', 'fk_purchase_quotes_need')
                    ->references('id')->on('purchase_needs')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('supplier_party_id', 'fk_purchase_quotes_supplier')
                    ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('currency_code', 'fk_purchase_quotes_currency')
                    ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->check('purchase_quotes', 'ck_purchase_quotes_supplier', '`supplier_party_id` IS NOT NULL OR `supplier_name` IS NOT NULL');
            $this->check('purchase_quotes', 'ck_purchase_quotes_amounts', '`quantity` > 0 AND `unit_price` >= 0 AND `total_amount` >= 0');
            $this->personnelForeignKeys('purchase_quotes');

            Schema::table('purchase_needs', function (Blueprint $table): void {
                $table->foreign('recommended_quote_id', 'fk_purchase_needs_recommended_quote')
                    ->references('id')->on('purchase_quotes')->restrictOnDelete()->restrictOnUpdate();
            });
        }
    }

    private function createCompanyPurchases(): void
    {
        if (Schema::hasTable('company_purchases')) {
            return;
        }

        Schema::create('company_purchases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('catalog_item_id');
            $table->unsignedBigInteger('purchase_need_id')->nullable();
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $table->unsignedBigInteger('personnel_id')->nullable();
            $table->unsignedBigInteger('supplier_party_id')->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->string('description', 300)->nullable();
            $table->date('purchased_on');
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_price', 20, 6)->default(0);
            $this->asciiChar($table, 'currency_code', 3);
            $table->unsignedSmallInteger('renewal_months')->nullable();
            $table->date('next_renewal_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('note')->nullable();
            $this->ts($table, 'archived_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('purchase_need_id', 'uk_company_purchases_need');
            $table->index('catalog_item_id', 'ix_company_purchases_item');
            $table->index('next_renewal_on', 'ix_company_purchases_renewal');
            $table->index('org_unit_id', 'ix_company_purchases_org_unit');
            $table->foreign('catalog_item_id', 'fk_company_purchases_item')
                ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('purchase_need_id', 'fk_company_purchases_need')
                ->references('id')->on('purchase_needs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('org_unit_id', 'fk_company_purchases_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_company_purchases_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('supplier_party_id', 'fk_company_purchases_supplier')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_company_purchases_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('company_purchases', 'ck_company_purchases_renewal', '`renewal_months` IS NULL OR `renewal_months` > 0');
        $this->check('company_purchases', 'ck_company_purchases_amounts', '`quantity` > 0 AND `unit_price` >= 0');
        $this->personnelForeignKeys('company_purchases');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (Schema::hasTable('purchase_needs')) {
            Schema::table('purchase_needs', function (Blueprint $table): void {
                $table->dropForeign('fk_purchase_needs_recommended_quote');
            });
        }

        foreach (['company_purchases', 'purchase_quotes', 'purchase_needs', 'cost_list_lines', 'cost_lists', 'catalog_item_aliases', 'catalog_items', 'catalog_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
