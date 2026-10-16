<?php

declare(strict_types=1);

namespace App\Support\Procurement;

use App\Models\Procurement\CatalogItem;
use App\Query\Reference\UnitQueries;
use App\Services\Platform\SchemaReadiness;
use App\Support\TurkishFold;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sabit birimler (D-194, B54; kullanici 12 Ekim 2026: "Sistemde birimler sabit
 * dropdown menu olmalidir. Urun secildiginde urune ozel birim otomatik gelip
 * alani doldurmalidir. Urun olusurken birimi belirlenmelidir yani.").
 *
 * - options() / label(): secim kutusunun secenekleri ("Metre (m)"); pasif
 *   birim listede yoktur, ama kayitta seciliyse adiyla gorunmeye devam eder.
 * - resolve(): Excel, yapistirma ve Konelsis AI metnini ("adet", "Mt.",
 *   "m²") birime cevirir: once B54 gocundeki takma ad tablosu, sonra kod /
 *   simge / ad (Turkce harfler katlanir). Yalniz aktif birimler.
 * - apply(): servislerin satir yazarken kullandigi tek kural: katalog
 *   kaleminin birimi varsa o (urunun birimi sabittir), yoksa secilen birim,
 *   yoksa metinden cozulen; `unit` metni birimin adiyla esitlenir (PDF ve eski
 *   ekranlar metni okur). B54 uygulanmadan `uom_id` hic yazilmaz, metin
 *   oldugu gibi kalir.
 *
 * Birim listesi istek basina bir kez okunur (#[Scoped]); Birimler ekrani
 * yazinca flush() ile yenilenir.
 */
#[Scoped]
final class UnitResolver
{
    /** B54 gocundeki takma adlar (katlanmis yazim => birim kodu); ikisi birlikte degisir. */
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

    /** Kayitlardaki `unit` metninin uzunlugu (B52 / B53 kolonlari). */
    public const TEXT_LENGTH = 40;

    /** @var array<int, array{id: int, dimension: string, code: string, symbol: string, name: string, active: bool}>|null */
    private ?array $units = null;

    /** @var array<string, int>|null katlanmis metin => birim (aktif) */
    private ?array $byText = null;

    /** @var array<string, int>|null kod => birim (aktif) */
    private ?array $byCode = null;

    /** @var array<int, int|null> katalog kalemi => birim */
    private array $itemUnits = [];

    /** @var array<string, int|null> */
    private array $resolved = [];

    public function __construct(private readonly UnitQueries $queries) {}

    /** B54 uygulandi mi? Degilse birim serbest metindir (eski davranis). */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B54');
    }

    /**
     * Secim kutusunun secenekleri: aktif birimler ("Ad (simge)"), ada gore.
     * `$include` pasif bir birimse (kayitta secili) o da eklenir.
     *
     * @return array<int, string>
     */
    public function options(mixed $include = null): array
    {
        $include = self::id($include);
        $options = [];

        foreach ($this->units() as $id => $unit) {
            if ($unit['active'] || $id === $include) {
                $options[$id] = $this->labelOf($unit);
            }
        }

        return $options;
    }

    /** Secili birimin etiketi ("Metre (m)"; pasifse "(pasif)" eki). */
    public function label(mixed $id): ?string
    {
        $unit = $this->units()[self::id($id) ?? 0] ?? null;

        return $unit !== null ? $this->labelOf($unit) : null;
    }

    /** Birimin adi (kayitlarda, PDF'te ve rozetlerde gorunen). */
    public function name(mixed $id): ?string
    {
        $unit = $this->units()[self::id($id) ?? 0] ?? null;

        return $unit !== null ? $unit['name'] : null;
    }

    /**
     * Metni birime cevirir (Excel, yapistirma, Konelsis AI): takma ad, kod,
     * simge ya da ad. Taninmazsa null.
     */
    public function resolve(mixed $text): ?int
    {
        $folded = self::fold((string) $text);

        if ($folded === '') {
            return null;
        }

        if (array_key_exists($folded, $this->resolved)) {
            return $this->resolved[$folded];
        }

        $this->index();
        $alias = self::ALIASES[$folded] ?? null;
        $id = ($alias !== null ? ($this->byCode[$alias] ?? null) : null) ?? ($this->byText[$folded] ?? null);

        return $this->resolved[$folded] = $id;
    }

    /** Katalog kaleminin birimi (secilince satira gelir ve kilitlenir). */
    public function itemUnitId(mixed $catalogItemId): ?int
    {
        $id = self::id($catalogItemId);

        if ($id === null || ! self::enabled()) {
            return null;
        }

        if (! array_key_exists($id, $this->itemUnits)) {
            $this->itemUnits[$id] = $this->queries->catalogItemUnits([$id])[$id] ?? null;
        }

        return $this->itemUnits[$id];
    }

    /**
     * Kayitli satirin gorunen birimi: B54'te birimin adi, yoksa eski metin.
     */
    public function display(?Model $record): ?string
    {
        if ($record === null) {
            return null;
        }

        if (self::enabled()) {
            $name = $this->name($record->getAttribute('uom_id'));

            if ($name !== null) {
                return $name;
            }
        }

        $text = trim((string) $record->getAttribute('unit'));

        return $text !== '' ? $text : null;
    }

    /**
     * Form durumundaki birim (kalem basligi rozeti): secili birimin adi, yoksa metin.
     *
     * @param  array<string, mixed>  $state
     */
    public function stateText(array $state): ?string
    {
        if (self::enabled() && ($name = $this->name($state['uom_id'] ?? null)) !== null) {
            return $name;
        }

        $text = trim((string) ($state['unit'] ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * Satirin birim alanlarini esitler (servisler). Kural: katalog kaleminin
     * birimi > secilen birim > metinden cozulen birim. Birim bulunursa `unit`
     * metni birimin adi olur; bulunmazsa metin korunur (sonradan secilir).
     * Ne `uom_id` ne `unit` verilmis ve kalemin birimi yoksa veri aynen doner.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function apply(array $values, ?CatalogItem $item = null): array
    {
        if (! self::enabled()) {
            unset($values['uom_id']);

            return $values;
        }

        $hasUom = array_key_exists('uom_id', $values);
        $hasText = array_key_exists('unit', $values);
        $itemUom = $item instanceof CatalogItem && $this->name($item->getAttribute('uom_id')) !== null
            ? (int) $item->getAttribute('uom_id')
            : null;

        if (! $hasUom && ! $hasText && $itemUom === null) {
            return $values;
        }

        $chosen = $hasUom && $this->name($values['uom_id']) !== null ? self::id($values['uom_id']) : null;
        $id = $itemUom ?? $chosen ?? ($hasText ? $this->resolve($values['unit']) : null);
        $values['uom_id'] = $id;

        if ($id !== null) {
            $values['unit'] = Str::limit((string) $this->name($id), self::TEXT_LENGTH, '');
        } elseif ($hasText) {
            $text = trim((string) $values['unit']);
            $values['unit'] = $text !== '' ? Str::limit($text, self::TEXT_LENGTH, '') : null;
        }

        return $values;
    }

    /** Birimler ekrani yazdiktan sonra liste yeniden okunur. */
    public function flush(): void
    {
        $this->units = null;
        $this->byText = null;
        $this->byCode = null;
        $this->itemUnits = [];
        $this->resolved = [];
    }

    /** Kucuk harf, Turkce harfler katlanir, bosluk kirpilir (B54 gocuyle ayni). */
    public static function fold(string $text): string
    {
        return TurkishFold::fold($text);
    }

    /**
     * @param  array{id: int, dimension: string, code: string, symbol: string, name: string, active: bool}  $unit
     */
    private function labelOf(array $unit): string
    {
        $label = self::fold($unit['symbol']) === '' || self::fold($unit['symbol']) === self::fold($unit['name'])
            ? $unit['name']
            : $unit['name'].' ('.$unit['symbol'].')';

        return $unit['active'] ? $label : $label.' '.__('units.values.inactive_suffix');
    }

    /**
     * @return array<int, array{id: int, dimension: string, code: string, symbol: string, name: string, active: bool}>
     */
    private function units(): array
    {
        if ($this->units === null) {
            $this->units = [];

            foreach ($this->queries->all() as $unit) {
                $this->units[$unit['id']] = $unit;
            }
        }

        return $this->units;
    }

    private function index(): void
    {
        if ($this->byText !== null && $this->byCode !== null) {
            return;
        }

        $this->byText = [];
        $this->byCode = [];
        $units = array_filter($this->units(), static fn (array $unit): bool => $unit['active']);
        // Gocteki gibi ilk kayit (en kucuk kimlik) kazanir.
        ksort($units);

        foreach ($units as $id => $unit) {
            $this->byCode[$unit['code']] ??= $id;

            foreach ([$unit['code'], $unit['symbol'], $unit['name']] as $text) {
                $folded = self::fold($text);

                if ($folded !== '') {
                    $this->byText[$folded] ??= $id;
                }
            }
        }
    }

    private static function id(mixed $value): ?int
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
