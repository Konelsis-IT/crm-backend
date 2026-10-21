<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B58 - Satin alma tedarikci listesi (D-232, 21 Ekim 2026 kullanici talimati:
 * "Satın alma için ayrı bir tedarikçi listesi oluşturalım bunun yapısı normal
 * taraflar ile aynı olacak ama menü olarak satın alma kısmında olacak. Hangi
 * alanda hizmet verdikleri de belli ... Taraflar kısmındaki tedarikçiler ile
 * buradakini birbirine karıştırmayalım"; ortak firmalar: "Firma tek olacak").
 *
 * Tedarikci bir taraf kaydidir (ayni yapi: unvan, kisiler, adresler, kanallar);
 * bu tablolar yalniz satin alma uyeligini tutar:
 *  - `purchase_service_areas`: hizmet alanlari (Excel'in grup basliklari:
 *    Salt ve Otomasyon, Kablo Ureticileri, GES Inverter (Yerli)...).
 *  - `purchase_suppliers`: tarafin tedarikci listesindeki kaydi; `purchase_only`
 *    = yalniz tedarikci (Taraflar'da gorunmez), 0 = ayni firma Taraflar'da da.
 *  - `purchase_supplier_areas`: tedarikcinin hizmet alanlari (coklu).
 *
 * On kosul: B16 (parties).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_service_areas')) {
            Schema::create('purchase_service_areas', function (Blueprint $table): void {
                $table->id();
                $this->code($table, 'code', 64);
                $table->string('name', 150);
                $table->unsignedInteger('sort_order')->default(0);
                $this->ts($table, 'archived_at')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->unique('code', 'uk_purchase_service_areas_code');
                $table->index(['archived_at', 'sort_order'], 'ix_purchase_service_areas_sort');
            });
            $this->personnelForeignKeys('purchase_service_areas');
        }

        if (! Schema::hasTable('purchase_suppliers')) {
            Schema::create('purchase_suppliers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('party_id');
                $table->boolean('purchase_only')->default(true);
                $table->text('note')->nullable();
                $this->ts($table, 'archived_at')->nullable();
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->unique('party_id', 'uk_purchase_suppliers_party');
                $table->index(['purchase_only', 'archived_at'], 'ix_purchase_suppliers_only');
                $table->foreign('party_id', 'fk_purchase_suppliers_party')
                    ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys('purchase_suppliers');
        }

        if (! Schema::hasTable('purchase_supplier_areas')) {
            Schema::create('purchase_supplier_areas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('purchase_supplier_id');
                $table->unsignedBigInteger('purchase_service_area_id');
                $this->auditCreated($table);

                $table->unique(['purchase_supplier_id', 'purchase_service_area_id'], 'uk_purchase_supplier_areas');
                $table->index('purchase_service_area_id', 'ix_purchase_supplier_areas_area');
                $table->foreign('purchase_supplier_id', 'fk_purchase_supplier_areas_supplier')
                    ->references('id')->on('purchase_suppliers')->cascadeOnDelete()->restrictOnUpdate();
                $table->foreign('purchase_service_area_id', 'fk_purchase_supplier_areas_area')
                    ->references('id')->on('purchase_service_areas')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys('purchase_supplier_areas');
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('purchase_supplier_areas');
        Schema::dropIfExists('purchase_suppliers');
        Schema::dropIfExists('purchase_service_areas');
    }
};
