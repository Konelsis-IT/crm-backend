<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B54 - Sabit birimler (D-194, 12 Ekim 2026 kullanici talimati: "birimler
 * sabit olacak ... Sistemde birimler sabit dropdown menu olmalidir. Urun
 * secildiginde urune ozel birim otomatik gelip alani doldurmalidir. Urun
 * olusurken birimi belirlenmelidir").
 *
 * Birim listesi B01'in `units_of_measure` tablosudur (Ayarlar > Birimler).
 * 1. Excel'de kullanilan ama listede olmayan dort birim eklenir: Ay, Yil, Wp,
 *    Delik (varsa dokunulmaz).
 * 2. `catalog_items.uom_id`: malzeme / hizmetin birimi. `purchase_needs`,
 *    `purchase_quotation_lines`, `purchase_order_lines` satirlarina da
 *    `uom_id`; eski `unit` metni belge goruntusu (PDF) icin kalir ve birimin
 *    adiyla esitlenir (servis).
 * 3. Var olan `unit` metinleri birime baglanir (Adet, Metre, Lot (eski adi Parti, burada Lot olur),
 *    Gun, Ton, MWp ...); taninmayan ya da bos metin bos kalir, kullanici
 *    kalemi duzenlerken birimi secer.
 *
 * Yalniz ekler ve bos `uom_id`'yi doldurur; var olan metin degismez. On kosul:
 * B01, B52, B53.
 */
return new class extends KonelsisMigration
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> boyut, kod, simge, ad, Ingilizce ad */
    private const NEW_UNITS = [
        ['time', 'month', 'ay', 'Ay', 'Month'],
        ['time', 'year', 'yıl', 'Yıl', 'Year'],
        ['power', 'Wp', 'Wp', 'Vat-pik', 'Watt-peak'],
        ['count', 'hole', 'delik', 'Delik', 'Hole'],
    ];

    /** @var array<string, string> katlanmis yazim => birim kodu */
    private const ALIASES = [
        'adet' => 'pcs', 'ad' => 'pcs', 'ad.' => 'pcs', 'pcs' => 'pcs', 'pc' => 'pcs', 'tane' => 'pcs',
        'set' => 'set', 'takim' => 'set',
        'lot' => 'lot', 'parti' => 'lot',
        'metre' => 'm', 'm' => 'm', 'mt' => 'm', 'mt.' => 'm', 'mtul' => 'm',
        'km' => 'km', 'kilometre' => 'km',
        'm2' => 'm2', 'm²' => 'm2', 'metrekare' => 'm2',
        'm3' => 'm3', 'm³' => 'm3', 'metrekup' => 'm3',
        'kg' => 'kg', 'kilogram' => 'kg',
        'ton' => 't', 't' => 't',
        'gun' => 'day', 'day' => 'day',
        'saat' => 'h', 'sa' => 'h',
        'ay' => 'month', 'yil' => 'year',
        'mwp' => 'MWp', 'kwp' => 'kWp', 'wp' => 'Wp', 'mw' => 'MW', 'kw' => 'kW',
        'delik' => 'hole',
        'litre' => 'l', 'lt' => 'l',
    ];

    /** @var list<string> */
    private const LINE_TABLES = ['purchase_needs', 'purchase_quotation_lines', 'purchase_order_lines'];

    public function up(): void
    {
        if (Schema::hasColumn('catalog_items', 'uom_id')) {
            return;
        }

        $this->addUnits();

        foreach (['catalog_items', ...self::LINE_TABLES] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->unsignedBigInteger('uom_id')->nullable()->after('unit');
                $blueprint->index('uom_id', $this->shorten("ix_{$table}_uom"));
                $blueprint->foreign('uom_id', $this->shorten("fk_{$table}_uom"))
                    ->references('id')->on('units_of_measure')->restrictOnDelete()->restrictOnUpdate();
            });

            $this->backfill($table);
        }
    }

    private function addUnits(): void
    {
        $now = Carbon::now('UTC');

        // Maliyet listelerinde "Lot" yazilir; B01'in varsayilan adi "Parti" idi.
        // Yalniz hala varsayilan ad duruyorsa degisir (elle verilmis ada dokunulmaz).
        DB::table('units_of_measure')
            ->where('dimension', 'count')
            ->where('code', 'lot')
            ->where('name_tr', 'Parti')
            ->update(['name_tr' => 'Lot', 'updated_at' => $now]);

        foreach (self::NEW_UNITS as [$dimension, $code, $symbol, $nameTr, $nameEn]) {
            DB::table('units_of_measure')->insertOrIgnore([
                'dimension' => $dimension,
                'code' => $code,
                'symbol' => $symbol,
                'name_tr' => $nameTr,
                'name_en' => $nameEn,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
                'row_version' => 1,
            ]);
        }
    }

    /** Var olan birim metnini birime baglar (yalniz bos uom_id). */
    private function backfill(string $table): void
    {
        $units = DB::table('units_of_measure')->get(['id', 'code', 'symbol', 'name_tr']);
        $byCode = [];
        $byText = [];

        foreach ($units as $unit) {
            $byCode[(string) $unit->code] ??= (int) $unit->id;

            foreach ([$unit->code, $unit->symbol, $unit->name_tr] as $text) {
                $byText[self::fold((string) $text)] ??= (int) $unit->id;
            }
        }

        $texts = DB::table($table)->whereNull('uom_id')->whereNotNull('unit')->where('unit', '!=', '')->distinct()->pluck('unit');

        foreach ($texts as $text) {
            $folded = self::fold((string) $text);
            $alias = self::ALIASES[$folded] ?? null;
            $uomId = ($alias !== null ? ($byCode[$alias] ?? null) : null) ?? ($byText[$folded] ?? null);

            if ($uomId !== null) {
                DB::table($table)->whereNull('uom_id')->where('unit', $text)->update(['uom_id' => $uomId]);
            }
        }
    }

    /** Kucuk harf, Turkce harfler katlanir, bosluk kirpilir. */
    private static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        return strtr($text, ['ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasColumn('catalog_items', 'uom_id')) {
            return;
        }

        foreach (['catalog_items', ...self::LINE_TABLES] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign($this->shorten("fk_{$table}_uom"));
                $blueprint->dropIndex($this->shorten("ix_{$table}_uom"));
                $blueprint->dropColumn('uom_id');
            });
        }
        // Eklenen birimler kalir (baska kayitlar kullaniyor olabilir).
    }
};
