<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B56 - Is kodu (D-204, 14 Ekim 2026 kullanici istegi: "islerin bir is kodu
 * yok mu islerin de bir is kodu olmalidir. varsa da yine is kolonu altinda
 * gorulsun").
 *
 * `work_items.item_no`: IS-YYYY-NNNN. Potansiyel is, teklif ve satin alma
 * kodlari gibi yila gore verilir (YearlyCodeAllocator, on ek IS; yil kurum
 * saatine gore). Yeni kartta kodu WorkItemService verir.
 *
 * Doldurma: kodu olmayan isler olusturulma sirasiyla, olusturuldugu yilin
 * sirasini alir. IS sayaci her yil icin verilen son numarayla baslar (sayac
 * satiri zaten varsa onun ustunden devam edilir). On kosul: B36 (work_items),
 * B40 (business_code_sequences).
 */
return new class extends KonelsisMigration
{
    private const PREFIX = 'IS';

    public function up(): void
    {
        if (! Schema::hasColumn('work_items', 'item_no')) {
            Schema::table('work_items', function (Blueprint $table): void {
                $this->code($table, 'item_no', 24)->nullable()->after('id');
                $table->unique('item_no', 'uk_work_items_item_no');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasColumn('work_items', 'item_no')) {
            return;
        }

        Schema::table('work_items', function (Blueprint $table): void {
            $table->dropUnique('uk_work_items_item_no');
            $table->dropColumn('item_no');
        });
        // IS sayaci kalir: verilmis kodlar yeniden kullanilmaz.
    }

    private function backfill(): void
    {
        /** @var array<int, int> $counters */
        $counters = [];

        $rows = DB::table('work_items')
            ->whereNull('item_no')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'created_at']);

        foreach ($rows as $row) {
            $year = $this->year($row->created_at);
            $counters[$year] ??= (int) (DB::table('business_code_sequences')
                ->where('code_prefix', self::PREFIX)
                ->where('code_year', $year)
                ->value('last_number') ?? 0);
            $number = ++$counters[$year];

            DB::table('work_items')->where('id', $row->id)->update([
                'item_no' => sprintf('%s-%d-%04d', self::PREFIX, $year, $number),
            ]);
        }

        foreach ($counters as $year => $last) {
            $exists = DB::table('business_code_sequences')
                ->where('code_prefix', self::PREFIX)
                ->where('code_year', $year)
                ->exists();

            if ($exists) {
                DB::table('business_code_sequences')
                    ->where('code_prefix', self::PREFIX)
                    ->where('code_year', $year)
                    ->update(['last_number' => $last]);

                continue;
            }

            DB::table('business_code_sequences')->insert([
                'code_prefix' => self::PREFIX,
                'code_year' => $year,
                'last_number' => $last,
            ]);
        }
    }

    /** Kurum saatine gore yil (kayitlar UTC, D-114). */
    private function year(mixed $value): int
    {
        $timezone = (string) config('konelsis.organization.default_timezone', 'Europe/Istanbul');

        return $value === null
            ? (int) Carbon::now($timezone)->format('Y')
            : (int) Carbon::parse((string) $value, 'UTC')->setTimezone($timezone)->format('Y');
    }
};
