<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B59 - Bir ise birden fazla teklif, is gelistirme kaydi ve ihale (D-233,
 * 21 Ekim 2026 kullanici karari: "Birden fazla bağlanabilsin").
 *
 * Isin ilk bagli kaydi eskisi gibi `work_items.link_kind` + `linked_*_id`
 * kolonundadir; ayni turden ya da diger iki turden ek kayitlar bu tablolarda
 * tutulur. Her tur kendi FK'siyla ayri tablodadir (polimorfik bag yoktur):
 *  - `work_item_proposals`: ise bagli ek teklifler.
 *  - `work_item_business_cases`: ise bagli ek is gelistirme kayitlari
 *    (potansiyel is / yatirimci projesi).
 *  - `work_item_tender_notices`: ise bagli ek ihaleler.
 *
 * Is silinirse satirlari da silinir; bagli kayit silinemez (restrict).
 * On kosul: B36 (work_items), B16 / acquisition tablolari.
 */
return new class extends KonelsisMigration
{
    /** @var array<string, array{0: string, 1: string, 2: string}> tablo => [kolon, hedef tablo, kisa ad] */
    private const TABLES = [
        'work_item_proposals' => ['proposal_id', 'proposals', 'proposal'],
        'work_item_business_cases' => ['business_case_id', 'business_cases', 'case'],
        'work_item_tender_notices' => ['tender_notice_id', 'tender_notices', 'tender'],
    ];

    public function up(): void
    {
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
    }
};
