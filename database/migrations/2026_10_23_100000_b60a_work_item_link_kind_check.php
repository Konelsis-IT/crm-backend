<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B60A - Isin ilk bagli kaydi Siparis ya da Urun / Hizmet olabilsin (D-238,
 * 23 Ekim 2026).
 *
 * B60 `work_items.linked_purchase_order_id` ve `linked_catalog_item_id`
 * kolonlarini ekledi; ancak `work_items.link_kind` kontrol kurali
 * (`ck_work_items_link_kind_enum`, B57) bu iki degeri tanimiyordu, isin ilk
 * bagli kaydi siparis ya da urun / hizmet olarak kaydedilemiyordu. Kural ayni
 * adla iki degerle yeniden kurulur; veri degismez.
 *
 * Uygulanana kadar Siparis ve Urun / Hizmet baglantisi ekranda gorunmez
 * (SchemaReadiness B60A). On kosul: B57, B60.
 */
return new class extends KonelsisMigration
{
    private const BASE = ['none', 'business_case', 'proposal', 'tender_notice', 'document', 'supply_item', 'work_request', 'meeting_plan', 'purchase_need'];

    public function up(): void
    {
        if (! Schema::hasColumn('work_items', 'linked_catalog_item_id')) {
            return;
        }

        $this->dropCheck('work_items', 'ck_work_items_link_kind_enum');
        $this->enumCheck('work_items', 'link_kind', [...self::BASE, 'purchase_order', 'catalog_item']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasColumn('work_items', 'linked_catalog_item_id')) {
            return;
        }

        $this->dropCheck('work_items', 'ck_work_items_link_kind_enum');
        DB::table('work_items')->whereIn('link_kind', ['purchase_order', 'catalog_item'])->update([
            'link_kind' => 'none',
            'linked_purchase_order_id' => null,
            'linked_catalog_item_id' => null,
        ]);
        $this->enumCheck('work_items', 'link_kind', self::BASE);
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $this->shorten($name)));
    }
};
