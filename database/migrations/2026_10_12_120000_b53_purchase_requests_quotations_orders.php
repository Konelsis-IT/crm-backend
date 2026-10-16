<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B53 - Kalemli satin alma talebi, satin alma teklifleri ve siparisler
 * (D-192, 12 Ekim 2026 kullanici talimati: "Birden fazla urun talebi
 * olabilir, o yuzden kalemli bir yapi olmasi lazim ... daha kapsamlisi Satin
 * alma teklifleri kismi olusturulup orada tutulmali ... Siparisler ise ilgili
 * teklif onaylandiginda olusan surec icin kullanilan bir yapidir. Tekliften
 * gelir. Secili teklif siparise donusur ... logistic sureci, teslim donemi,
 * teslim adresi ... proje, teklif grubundan gelen toplu tedarik urunlerinin
 * topluca checkbox olarak secilip topluca teklifte birlestirilip ortak bir
 * siparise donusturulebilmesi").
 *
 * 1. `purchase_requests`: satin alma talebinin basligi (SAT-YYYY-NNNN; talep
 *    eden, departman, kullanim yeri, proje, termin, acil, gerekce, var olanin
 *    neden kullanilamadigi). Kalemleri `purchase_needs.purchase_request_id`.
 * 2. `purchase_quotations`: tedarikci teklifi (STKF-YYYY-NNNN; tedarikci,
 *    tedarikcinin teklif no, tarih, gecerlilik, para birimi, TL kuru, odeme ve
 *    teslim sartlari, genel indirim, toplamlar, durum, Konelsis AI durumu).
 *    `purchase_quotation_lines`: kalemler (Satin alinacaklar'daki kaleme ve
 *    katalog kalemine bagli olabilir; miktar, birim fiyat, indirim, opsiyon,
 *    iptal, AI'nin actigi katalog kalemi isareti). `purchase_quotation_documents`:
 *    yuklenen teklif belgeleri ve AI analizinin sonucu.
 * 3. `purchase_orders`: secili tekliften siparis (SIP-YYYY-NNNN; onay talebi,
 *    teslim adresi ve donemi, lojistik: tasiyici, takip no, beklenen teslim).
 *    `purchase_order_lines`: teklif kalemlerinden; teslim alinan miktar.
 * 4. `company_purchases`: talebe ve siparise baglanir (kalemli sirket ici alim).
 *
 * B52'nin `purchase_quotes` tablosu ve `purchase_needs.recommended_quote_id`
 * artik kullanilmaz (bos kalir); kaldirilmasi DBA'nin karari. Veri tasima yok
 * (yerelde siparis kalemi ve teklif yok). On kosul: B52, B52A.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const REQUEST_STATUSES = ['open', 'in_progress', 'completed', 'cancelled'];

    /** @var list<string> */
    private const QUOTATION_STATUSES = ['draft', 'received', 'selected', 'rejected', 'ordered', 'cancelled'];

    /** @var list<string> */
    private const AI_STATUSES = ['pending', 'completed', 'failed'];

    /** @var list<string> */
    private const DOCUMENT_AI_STATUSES = ['pending', 'quotation', 'not_quotation', 'failed'];

    /** @var list<string> */
    private const ORDER_STATUSES = ['draft', 'awaiting_approval', 'approved', 'ordered', 'in_transit', 'delivered', 'completed', 'cancelled'];

    /** @var list<string> */
    private const USAGE_AREAS = ['office', 'field'];

    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            return;
        }

        $this->createRequests();
        $this->createQuotations();
        $this->createOrders();
        $this->linkCompanyPurchases();
    }

    private function createRequests(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'request_no', 32);
            $table->unsignedBigInteger('requested_by_personnel_id')->nullable();
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $this->status($table, 'usage_area')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->date('needed_by')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->text('justification')->nullable();
            $table->text('existing_check')->nullable();
            $table->text('note')->nullable();
            $this->status($table);
            $this->ts($table, 'archived_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('request_no', 'uk_purchase_requests_no');
            $table->index(['status', 'needed_by'], 'ix_purchase_requests_status_needed');
            $table->index('requested_by_personnel_id', 'ix_purchase_requests_requested_by');
            $table->foreign('requested_by_personnel_id', 'fk_purchase_requests_requested_by')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('org_unit_id', 'fk_purchase_requests_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_id', 'fk_purchase_requests_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('purchase_requests', 'status', self::REQUEST_STATUSES);
        $this->nullableEnumCheck('purchase_requests', 'usage_area', self::USAGE_AREAS);
        $this->personnelForeignKeys('purchase_requests');

        Schema::table('purchase_needs', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_request_id')->nullable()->after('source');
            $table->unsignedInteger('sort_order')->default(0)->after('purchase_request_id');
            $table->index('purchase_request_id', 'ix_purchase_needs_request');
            $table->foreign('purchase_request_id', 'fk_purchase_needs_request')
                ->references('id')->on('purchase_requests')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    private function createQuotations(): void
    {
        Schema::create('purchase_quotations', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'quotation_no', 32);
            $table->unsignedBigInteger('supplier_party_id')->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->string('supplier_reference', 100)->nullable();
            $table->date('quote_date')->nullable();
            $table->date('valid_until')->nullable();
            $this->asciiChar($table, 'currency_code', 3);
            $table->decimal('try_rate', 20, 6)->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->string('delivery_terms', 255)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->date('delivery_on')->nullable();
            $table->decimal('general_discount', 20, 4)->default(0);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $this->status($table);
            $table->unsignedBigInteger('selected_by_personnel_id')->nullable();
            $this->ts($table, 'selected_at')->nullable();
            $this->status($table, 'ai_status')->nullable();
            $table->string('ai_job_reference', 100)->nullable();
            $this->ts($table, 'ai_requested_at')->nullable();
            $table->text('note')->nullable();
            $this->ts($table, 'archived_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('quotation_no', 'uk_purchase_quotations_no');
            $table->index(['status', 'quote_date'], 'ix_purchase_quotations_status_date');
            $table->index('supplier_party_id', 'ix_purchase_quotations_supplier');
            $table->foreign('supplier_party_id', 'fk_purchase_quotations_supplier')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_purchase_quotations_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('selected_by_personnel_id', 'fk_purchase_quotations_selected_by')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('purchase_quotations', 'status', self::QUOTATION_STATUSES);
        $this->nullableEnumCheck('purchase_quotations', 'ai_status', self::AI_STATUSES);
        $this->personnelForeignKeys('purchase_quotations');

        Schema::create('purchase_quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('purchase_quotation_id');
            $table->unsignedBigInteger('purchase_need_id')->nullable();
            $table->unsignedBigInteger('catalog_item_id')->nullable();
            $table->string('description', 500);
            $table->string('brand_model', 200)->nullable();
            $table->decimal('quantity', 20, 4)->default(1);
            $table->string('unit', 40)->nullable();
            $table->decimal('unit_price', 20, 6)->default(0);
            $table->decimal('discount_rate', 7, 4)->default(0);
            $table->decimal('line_total', 20, 4)->default(0);
            $table->boolean('is_option')->default(false);
            $table->boolean('is_cancelled')->default(false);
            $table->boolean('ai_created_item')->default(false);
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['purchase_quotation_id', 'sort_order'], 'ix_purchase_quotation_lines_quotation');
            $table->index('purchase_need_id', 'ix_purchase_quotation_lines_need');
            $table->index('catalog_item_id', 'ix_purchase_quotation_lines_item');
            $table->foreign('purchase_quotation_id', 'fk_purchase_quotation_lines_quotation')
                ->references('id')->on('purchase_quotations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('purchase_need_id', 'fk_purchase_quotation_lines_need')
                ->references('id')->on('purchase_needs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('catalog_item_id', 'fk_purchase_quotation_lines_item')
                ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('purchase_quotation_lines', 'ck_purchase_quotation_lines_amounts', '`quantity` > 0 AND `unit_price` >= 0 AND `discount_rate` >= 0 AND `discount_rate` <= 100');
        $this->personnelForeignKeys('purchase_quotation_lines');

        Schema::create('purchase_quotation_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('purchase_quotation_id');
            $table->unsignedBigInteger('document_revision_id');
            $this->status($table, 'ai_status')->nullable();
            $table->text('ai_summary')->nullable();
            $this->auditCreated($table);

            $table->unique(['purchase_quotation_id', 'document_revision_id'], 'uk_purchase_quotation_documents_revision');
            $table->index('document_revision_id', 'ix_purchase_quotation_documents_revision');
            $table->foreign('purchase_quotation_id', 'fk_purchase_quotation_documents_quotation')
                ->references('id')->on('purchase_quotations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_purchase_quotation_documents_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->nullableEnumCheck('purchase_quotation_documents', 'ai_status', self::DOCUMENT_AI_STATUSES);
        $this->personnelForeignKeys('purchase_quotation_documents');
    }

    private function createOrders(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'order_no', 32);
            $table->unsignedBigInteger('purchase_quotation_id');
            $table->unsignedBigInteger('supplier_party_id')->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $this->asciiChar($table, 'currency_code', 3);
            $table->decimal('try_rate', 20, 6)->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $this->status($table);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_contact', 200)->nullable();
            $table->date('delivery_from')->nullable();
            $table->date('delivery_until')->nullable();
            $table->date('expected_delivery_on')->nullable();
            $table->string('carrier', 200)->nullable();
            $table->string('tracking_no', 100)->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->unsignedBigInteger('ordered_by_personnel_id')->nullable();
            $this->ts($table, 'ordered_at')->nullable();
            $this->ts($table, 'shipped_at')->nullable();
            $this->ts($table, 'delivered_at')->nullable();
            $table->unsignedBigInteger('completed_by_personnel_id')->nullable();
            $this->ts($table, 'completed_at')->nullable();
            $this->ts($table, 'cancelled_at')->nullable();
            $table->text('note')->nullable();
            $this->ts($table, 'archived_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('order_no', 'uk_purchase_orders_no');
            $table->unique('purchase_quotation_id', 'uk_purchase_orders_quotation');
            $table->index(['status', 'expected_delivery_on'], 'ix_purchase_orders_status_delivery');
            $table->foreign('purchase_quotation_id', 'fk_purchase_orders_quotation')
                ->references('id')->on('purchase_quotations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('supplier_party_id', 'fk_purchase_orders_supplier')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_id', 'fk_purchase_orders_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_purchase_orders_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approval_request_id', 'fk_purchase_orders_approval_request')
                ->references('id')->on('approval_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('ordered_by_personnel_id', 'fk_purchase_orders_ordered_by')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('completed_by_personnel_id', 'fk_purchase_orders_completed_by')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('purchase_orders', 'status', self::ORDER_STATUSES);
        $this->check('purchase_orders', 'ck_purchase_orders_delivery_period', '`delivery_until` IS NULL OR `delivery_from` IS NULL OR `delivery_until` >= `delivery_from`');
        $this->personnelForeignKeys('purchase_orders');

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_quotation_line_id')->nullable();
            $table->unsignedBigInteger('purchase_need_id')->nullable();
            $table->unsignedBigInteger('catalog_item_id')->nullable();
            $table->string('description', 500);
            $table->string('brand_model', 200)->nullable();
            $table->decimal('quantity', 20, 4)->default(1);
            $table->string('unit', 40)->nullable();
            $table->decimal('unit_price', 20, 6)->default(0);
            $table->decimal('discount_rate', 7, 4)->default(0);
            $table->decimal('line_total', 20, 4)->default(0);
            $table->decimal('received_quantity', 20, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['purchase_order_id', 'sort_order'], 'ix_purchase_order_lines_order');
            $table->unique('purchase_quotation_line_id', 'uk_purchase_order_lines_quotation_line');
            $table->index('purchase_need_id', 'ix_purchase_order_lines_need');
            $table->foreign('purchase_order_id', 'fk_purchase_order_lines_order')
                ->references('id')->on('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('purchase_quotation_line_id', 'fk_purchase_order_lines_quotation_line')
                ->references('id')->on('purchase_quotation_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('purchase_need_id', 'fk_purchase_order_lines_need')
                ->references('id')->on('purchase_needs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('catalog_item_id', 'fk_purchase_order_lines_item')
                ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('purchase_order_lines', 'ck_purchase_order_lines_amounts', '`quantity` > 0 AND `received_quantity` >= 0');
        $this->personnelForeignKeys('purchase_order_lines');
    }

    private function linkCompanyPurchases(): void
    {
        Schema::table('company_purchases', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_request_id')->nullable()->after('purchase_need_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->after('purchase_request_id');
            $table->index('purchase_request_id', 'ix_company_purchases_request');
            $table->index('purchase_order_id', 'ix_company_purchases_order');
            $table->foreign('purchase_request_id', 'fk_company_purchases_request')
                ->references('id')->on('purchase_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('purchase_order_id', 'fk_company_purchases_order')
                ->references('id')->on('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('company_purchases', function (Blueprint $table): void {
            $table->dropForeign('fk_company_purchases_request');
            $table->dropForeign('fk_company_purchases_order');
            $table->dropIndex('ix_company_purchases_request');
            $table->dropIndex('ix_company_purchases_order');
            $table->dropColumn(['purchase_request_id', 'purchase_order_id']);
        });

        Schema::table('purchase_needs', function (Blueprint $table): void {
            $table->dropForeign('fk_purchase_needs_request');
            $table->dropIndex('ix_purchase_needs_request');
            $table->dropColumn(['purchase_request_id', 'sort_order']);
        });

        foreach (['purchase_order_lines', 'purchase_orders', 'purchase_quotation_documents', 'purchase_quotation_lines', 'purchase_quotations', 'purchase_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function nullableEnumCheck(string $table, string $column, array $values): void
    {
        $list = implode(', ', array_map(static fn (string $value): string => "'".$value."'", $values));
        $this->check($table, "ck_{$table}_{$column}_enum", "`{$column}` IS NULL OR `{$column}` IN ({$list})");
    }
};
