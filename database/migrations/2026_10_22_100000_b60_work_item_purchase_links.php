<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B60 - Ise satin alma siparisi ve urun / hizmet baglantisi; butun bagli kayit
 * turleri icin birden fazla baglanti (D-235, 22 Ekim 2026 kullanici talimati:
 * "Ekstra olarak satin alma da yayina girdikce satin almanin secenekleri de
 * eklensin. Belge, Urun Hizmet, Talep, Gorusme, Siparis, Satin alinacak urun
 * gibi gibi"; D-233 karari "Birden fazla baglanabilsin" bu turlerin hepsi icin
 * gecerlidir).
 *
 * 1) Ilk bagli kayit icin iki yeni tur; her tur kendi FK kolonundadir
 *    (polimorfik bag yoktur):
 *    - `work_items.linked_purchase_order_id` -> purchase_orders (Siparis).
 *    - `work_items.linked_catalog_item_id` -> catalog_items (Urun / Hizmet).
 * 2) Ek bagli kayit tablolari (B59'daki teklif / is gelistirme / ihale
 *    tablolariyla ayni bicim): belge, talep, gorusme, satin alinacak kalem,
 *    siparis, urun / hizmet. Is silinirse satirlari da silinir; bagli kayit
 *    silinemez (restrict).
 *
 * On kosul: B36 (work_items), B57 (purchase_needs baglantisi), B52
 * (catalog_items), B53 (purchase_orders), B59.
 */
return new class extends KonelsisMigration
{
    /** @var array<string, array{0: string, 1: string, 2: string}> tablo => [kolon, hedef tablo, kisa ad] */
    private const TABLES = [
        'work_item_documents' => ['document_id', 'documents', 'document'],
        'work_item_work_requests' => ['work_request_id', 'work_requests', 'request'],
        'work_item_meeting_plans' => ['meeting_plan_id', 'meeting_plans', 'meeting'],
        'work_item_purchase_needs' => ['purchase_need_id', 'purchase_needs', 'need'],
        'work_item_purchase_orders' => ['purchase_order_id', 'purchase_orders', 'order'],
        'work_item_catalog_items' => ['catalog_item_id', 'catalog_items', 'catalog'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('work_items', 'linked_purchase_order_id')) {
            Schema::table('work_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('linked_purchase_order_id')->nullable()->after('linked_purchase_need_id');
                $table->index('linked_purchase_order_id', 'ix_work_items_linked_order');
                $table->foreign('linked_purchase_order_id', 'fk_work_items_linked_order')
                    ->references('id')->on('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            });
        }

        if (! Schema::hasColumn('work_items', 'linked_catalog_item_id')) {
            Schema::table('work_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('linked_catalog_item_id')->nullable()->after('linked_purchase_order_id');
                $table->index('linked_catalog_item_id', 'ix_work_items_linked_catalog');
                $table->foreign('linked_catalog_item_id', 'fk_work_items_linked_catalog')
                    ->references('id')->on('catalog_items')->restrictOnDelete()->restrictOnUpdate();
            });
        }

        foreach (self::TABLES as $name => [$column, $target, $short]) {
            if (Schema::hasTable($name)) {
                continue;
            }

            Schema::create($name, function (Blueprint $table) use ($name, $column, $target, $short): void {
                $table->id();
                $table->unsignedBigInteger('work_item_id');
                $table->unsignedBigInteger($column);
                $this->auditCreated($table);

                $table->unique(['work_item_id', $column], 'uk_'.$name);
                $table->index($column, 'ix_'.$name.'_'.$short);
                $table->foreign('work_item_id', 'fk_'.$name.'_item')
                    ->references('id')->on('work_items')->cascadeOnDelete()->restrictOnUpdate();
                $table->foreign($column, 'fk_'.$name.'_'.$short)
                    ->references('id')->on($target)->restrictOnDelete()->restrictOnUpdate();
            });
            $this->personnelForeignKeys($name);
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach (array_reverse(array_keys(self::TABLES)) as $name) {
            Schema::dropIfExists($name);
        }

        Schema::table('work_items', function (Blueprint $table): void {
            if (Schema::hasColumn('work_items', 'linked_catalog_item_id')) {
                $table->dropForeign('fk_work_items_linked_catalog');
                $table->dropColumn('linked_catalog_item_id');
            }

            if (Schema::hasColumn('work_items', 'linked_purchase_order_id')) {
                $table->dropForeign('fk_work_items_linked_order');
                $table->dropColumn('linked_purchase_order_id');
            }
        });
    }
};
