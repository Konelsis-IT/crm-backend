<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B62 - Is kategorilerinin birlesmesi (D-245, 23 Ekim 2026 kullanici: Huseyin
 * Gunes'in kategori listesi; "Bugunku birlesik kategoriler ... Ilk karsiliga
 * tasinsin").
 *
 * Sema degismez; yalniz kayitli kategori kodu yeni karsiligina tasinir:
 * it_media -> it, hr_isg -> hr, software_commissioning -> software,
 * new_investments ve existing_investments -> investments. Isler ve gorev ve
 * sorumluluklar (tekrarli isler) icin. Is grubu (discipline) degerlerine
 * dokunulmaz; alan arayuzde gosterilmez (veritabaninda kalir).
 *
 * Geri alinamaz: hangi isin hangi birlesik koddan geldigi tutulmaz; down()
 * bilincli olarak bostur (ekran eski kodlari da tanir, RETIRED).
 *
 * On kosul: B36 (work_items), B57 (recurring_works).
 */
return new class extends KonelsisMigration
{
    /** Eski kod => yeni kod. */
    private const MAP = [
        'it_media' => 'it',
        'hr_isg' => 'hr',
        'software_commissioning' => 'software',
        'new_investments' => 'investments',
        'existing_investments' => 'investments',
    ];

    public function up(): void
    {
        foreach (['work_items', 'recurring_works'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'category_code')) {
                continue;
            }

            foreach (self::MAP as $from => $to) {
                DB::table($table)->where('category_code', $from)->update(['category_code' => $to]);
            }
        }
    }

    public function down(): void
    {
        // Birlesme geri alinamaz (bkz. aciklama).
    }
};
