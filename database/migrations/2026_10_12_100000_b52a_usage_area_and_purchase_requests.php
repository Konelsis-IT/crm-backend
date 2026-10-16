<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B52A - Kullanim yeri ve satin alma talebi (D-190, 12 Ekim 2026 kullanici
 * talimati: "Sirket ici ayri bir menu olsa da direk turu farkli olamaz ki.
 * Cunku sirket icindeki bilgisayar ayni zamanda urundur, Claude aboneligi ayni
 * zamanda bir hizmettir ... Sirket ici / Saha diye urunun ana kullanilacagi yer
 * secilebilir, ayri bir kolon olabilir" ve sat-03 "Satin alma talebi ayri bir
 * tur: talepte urun (marka, model; varsa ayni urun secilir) ve gerekce ... var
 * olan neden kullanilmadi ... talep tanimli olur (or. bilgisayar icin islemci
 * ve ozellikler)").
 *
 * 1. Katalog turlerinden "Sirket ici" (office) kalkar; urun / hizmet olarak
 *    kalir. `catalog_items.usage_area` (office Sirket ici, field Saha; bos =
 *    belirtilmedi) kalemin ana kullanim yeridir. Var olan Sirket ici kalemleri
 *    urun (Yazilim ve abonelikler kategorisindekiler hizmet) olur ve kullanim
 *    yeri Sirket ici; Sirket ici kategorileri urun / hizmet agacina gecer.
 *    Excel'den gelen urun / hizmet kalemlerinin kullanim yeri Saha olur.
 *    Tur CHECK'leri yeni listeyle yeniden yazilir.
 * 2. `purchase_needs`: kullanim yeri; katalogda olmayan urun icin talep edilen
 *    ad (`requested_item_name`, satin alma sonra katalogla eslestirir; bu
 *    yuzden `catalog_item_id` bos olabilir, ikisinden biri zorunlu), istenen
 *    marka / model, istenen ozellikler, gerekce ve "var olan neden
 *    kullanilmadi" (`existing_check`).
 *
 * Yerelde B52'nin uzerine calisir; canlida B52 ile ayni kurulumda, seed'den
 * once (Sirket ici kalem yoktur). On kosul: B52.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const KINDS = ['product', 'service', 'general_expense', 'admin_staff'];

    /** @var list<string> */
    private const OLD_KINDS = ['product', 'service', 'general_expense', 'admin_staff', 'office'];

    /** @var list<string> */
    private const USAGE_AREAS = ['office', 'field'];

    public function up(): void
    {
        if (! Schema::hasTable('catalog_items') || Schema::hasColumn('purchase_needs', 'justification')) {
            return;
        }

        Schema::table('catalog_items', function (Blueprint $table): void {
            $this->status($table, 'usage_area')->nullable()->after('kind');
            $table->index('usage_area', 'ix_catalog_items_usage_area');
        });
        $this->nullableEnumCheck('catalog_items', 'usage_area', self::USAGE_AREAS);

        // Sirket ici kategoriler urun / hizmet agacina; Yazilim ve abonelikler kalemleri hizmet.
        $serviceCategoryIds = DB::table('catalog_categories')
            ->where('kind', 'office')
            ->where('name', 'Yazılım ve abonelikler')
            ->pluck('id')
            ->all();

        DB::table('catalog_items')->where('kind', 'office')->whereIn('catalog_category_id', $serviceCategoryIds === [] ? [0] : $serviceCategoryIds)
            ->update(['kind' => 'service', 'usage_area' => 'office']);
        DB::table('catalog_items')->where('kind', 'office')->update(['kind' => 'product', 'usage_area' => 'office']);
        DB::table('catalog_items')->whereIn('kind', ['product', 'service'])->whereNull('usage_area')->update(['usage_area' => 'field']);
        DB::table('catalog_categories')->where('kind', 'office')->update(['kind' => 'product']);

        $this->replaceEnumCheck('catalog_items', 'kind', self::KINDS);
        $this->replaceEnumCheck('catalog_categories', 'kind', self::KINDS);

        Schema::table('purchase_needs', function (Blueprint $table): void {
            $table->unsignedBigInteger('catalog_item_id')->nullable()->change();
            $this->status($table, 'usage_area')->nullable()->after('source');
            $table->string('requested_item_name', 300)->nullable()->after('catalog_item_id');
            $table->string('brand_model', 200)->nullable()->after('requested_item_name');
            $table->text('specification')->nullable()->after('brand_model');
            $table->text('justification')->nullable()->after('specification');
            $table->text('existing_check')->nullable()->after('justification');
            $table->index('usage_area', 'ix_purchase_needs_usage_area');
        });
        $this->nullableEnumCheck('purchase_needs', 'usage_area', self::USAGE_AREAS);
        $this->check('purchase_needs', 'ck_purchase_needs_item_or_name', '`catalog_item_id` IS NOT NULL OR `requested_item_name` IS NOT NULL');

        // Var olan siparis kalemleri: teklif maliyetinden gelen Saha, digerleri kalemin yeri.
        DB::table('purchase_needs')->where('source', 'proposal_cost')->update(['usage_area' => 'field']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasColumn('purchase_needs', 'justification')) {
            return;
        }

        $this->dropCheck('purchase_needs', 'ck_purchase_needs_item_or_name');
        $this->dropCheck('purchase_needs', 'ck_purchase_needs_usage_area_enum');

        Schema::table('purchase_needs', function (Blueprint $table): void {
            $table->dropIndex('ix_purchase_needs_usage_area');
            $table->dropColumn(['usage_area', 'requested_item_name', 'brand_model', 'specification', 'justification', 'existing_check']);
        });

        // Katalogsuz kalem varsa catalog_item_id NOT NULL yapilamaz; DBA once o satirlari ele alir.
        Schema::table('purchase_needs', function (Blueprint $table): void {
            $table->unsignedBigInteger('catalog_item_id')->nullable(false)->change();
        });

        $this->replaceEnumCheck('catalog_items', 'kind', self::OLD_KINDS);
        $this->replaceEnumCheck('catalog_categories', 'kind', self::OLD_KINDS);
        $this->dropCheck('catalog_items', 'ck_catalog_items_usage_area_enum');

        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->dropIndex('ix_catalog_items_usage_area');
            $table->dropColumn('usage_area');
        });
    }

    /**
     * @param  list<string>  $values
     */
    private function nullableEnumCheck(string $table, string $column, array $values): void
    {
        $list = implode(', ', array_map(static fn (string $value): string => "'".$value."'", $values));
        $this->check($table, "ck_{$table}_{$column}_enum", "`{$column}` IS NULL OR `{$column}` IN ({$list})");
    }

    /**
     * Var olan tur CHECK'ini kaldirip yeni listeyle ekler (yalniz MySQL).
     *
     * @param  list<string>  $values
     */
    private function replaceEnumCheck(string $table, string $column, array $values): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $this->dropCheck($table, "ck_{$table}_{$column}_enum");
        $this->enumCheck($table, $column, $values);
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $name = $this->shorten($name);
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'CHECK'],
        );

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $name));
        }
    }
};
