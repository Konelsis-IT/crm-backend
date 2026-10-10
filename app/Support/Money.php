<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;

/**
 * Tutar gosterimi ve girisi icin tek kaynak (D-180, 8 Ekim 2026 kullanici
 * talimati: "1000 => 1.000, 1000,50 => 1.000,50 yazmali; yaninda ilgili para
 * biriminin simgesi gorulmeli; 50000 TRY gibi bir ifade uygulamanin hicbir
 * yerinde gorulmemeli").
 *
 * Kural (her yerde ayni):
 * - Sayi Turkce yazilir: binlik ayrac nokta, ondalik ayrac virgul.
 * - Kurus yoksa ondalik yazilmaz ("1.000"); varsa iki basamak ("1.000,50").
 *   Giris maskesi de ayni metni uretir, boylece girilen ve gosterilen ayni.
 * - Para birimi ISO kodu yerine simgesiyle, sayinin ARKASINDA, bolunmez
 *   bosluklu yazilir: "1.000,50 [TL simgesi U+20BA]", "1.000,50 $",
 *   "1.000,50 [Euro simgesi U+20AC]", "1.000,50 lei" (Rumen leyinin tek
 *   karakterli simgesi yok), "1.000,50 [Sterlin simgesi U+00A3]".
 *   Tabloda olmayan kod son care olarak kodun kendisiyle yazilir.
 * - Veritabaninda ISO kodu (currency_code) ve duz ondalik sayi saklanir;
 *   donusum yalniz ekran / disa aktarim / giris katmanindadir.
 *
 * Giris alani: App\Filament\Forms\Components\MoneyInput. Tablo ve kayit
 * alanlari: App\Filament\Support\MoneyDisplay. React panolari simgeleri
 * yapilandirmadan alir (DashboardAppConfig `currency_symbols`).
 */
final class Money
{
    /** ISO kodu => ekranda gosterilen simge (kaynak kod ASCII kalsin diye \u kacisli). */
    public const SYMBOLS = [
        'TRY' => "\u{20BA}",
        'USD' => '$',
        'EUR' => "\u{20AC}",
        'GBP' => "\u{00A3}",
        'RON' => 'lei',
    ];

    /** Sayi ile simge arasindaki bolunmez bosluk (satir sonunda ayrilmasin). */
    private const GAP = "\u{00A0}";

    /** Kurumun varsayilan para birimi (konelsis.organization.default_currency). */
    public static function defaultCurrency(): string
    {
        $code = config('konelsis.organization.default_currency', 'TRY');

        return is_string($code) && $code !== '' ? strtoupper($code) : 'TRY';
    }

    /** ISO kodu normalize eder; bos ise null. */
    public static function code(mixed $currency): ?string
    {
        if ($currency instanceof BackedEnum) {
            $currency = $currency->value;
        }

        if (! is_string($currency) || trim($currency) === '') {
            return null;
        }

        return strtoupper(trim($currency));
    }

    /**
     * Para biriminin simgesi (TL simgesi, "$", Euro simgesi, "lei"); tabloda
     * yoksa ISO kodu.
     * Kod bos ise $fallbackToDefault true iken kurum para biriminin simgesi,
     * degilse bos metin.
     */
    public static function symbol(mixed $currency, bool $fallbackToDefault = false): string
    {
        $code = self::code($currency) ?? ($fallbackToDefault ? self::defaultCurrency() : null);

        if ($code === null) {
            return '';
        }

        return self::SYMBOLS[$code] ?? $code;
    }

    /**
     * Secim listesi ve kayit alani icin para birimi adi: "[TL simgesi] Turk
     * lirasi", "[Euro simgesi] Euro", "lei Rumen leyi". Ad
     * lang/{tr,en}/money.php'den, yoksa
     * verilen addan (currencies.name_tr), o da yoksa ISO kodundan gelir.
     */
    public static function label(mixed $currency, ?string $name = null): string
    {
        $code = self::code($currency);

        if ($code === null) {
            return '-';
        }

        $key = 'money.names.'.$code;
        $translated = __($key);
        $text = is_string($translated) && $translated !== $key
            ? $translated
            : (is_string($name) && trim($name) !== '' ? trim($name) : $code);

        $symbol = self::symbol($code);

        return $symbol === $code && $text === $code ? $code : $symbol.' '.$text;
    }

    /**
     * Tutar + simge: "1.000,50 [simge]". Tutar bos ya da sayi degilse $empty.
     * Para birimi bos ise yalniz sayi yazilir.
     */
    public static function format(mixed $amount, mixed $currency, string $empty = '-', int $maxDecimals = 2): string
    {
        $number = self::number($amount, $maxDecimals);

        if ($number === null) {
            return $empty;
        }

        $symbol = self::symbol($currency);

        return $symbol === '' ? $number : $number.self::GAP.$symbol;
    }

    /**
     * Para birimi basina toplamlari "1.000 [TL simgesi] / 250 [Euro simgesi]"
     * gibi orta noktayla ayirip yazar; hic tutar yoksa $empty.
     *
     * @param  array<string, float|int|string|null>  $sums  ISO kodu => toplam
     */
    public static function sums(array $sums, string $empty = '-'): string
    {
        $parts = [];

        foreach ($sums as $currency => $amount) {
            $text = self::format($amount, (string) $currency, '');

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $parts === [] ? $empty : implode(" \u{00B7} ", $parts);
    }

    /**
     * Simgesiz Turkce sayi metni: 1000 => "1.000", 1000.5 => "1.000,50".
     * Giris alaninin gosterdigi metin de budur. Kurus en fazla $maxDecimals
     * basamak; sondaki sifirlar ikinci basamaga kadar silinir.
     */
    public static function number(mixed $amount, int $maxDecimals = 2): ?string
    {
        $value = self::parse($amount);

        if ($value === null) {
            return null;
        }

        $maxDecimals = max(0, min(6, $maxDecimals));
        $fixed = number_format(abs($value), $maxDecimals, '.', '');
        [$integer, $fraction] = array_pad(explode('.', $fixed, 2), 2, '');
        $fraction = rtrim($fraction, '0');

        if ($fraction !== '' && strlen($fraction) < 2) {
            $fraction = str_pad($fraction, 2, '0');
        }

        $grouped = ltrim(strrev(chunk_split(strrev($integer), 3, '.')), '.');
        $negative = $value < 0 && ($integer !== '0' || $fraction !== '');

        return ($negative ? '-' : '').$grouped.($fraction !== '' ? ','.$fraction : '');
    }

    /**
     * Giris alaninin metni (D-185): kurus her zaman sabit $decimals basamak
     * yazilir: 1000 => "1.000,00", 1000.5 => "1.000,50"; 4 ondalikli alanda
     * "1.000,5000". Gosterim kurali (number) degismez.
     */
    public static function fixed(mixed $amount, int $decimals = 2): ?string
    {
        $value = self::parse($amount);

        if ($value === null) {
            return null;
        }

        $decimals = max(0, min(6, $decimals));
        [$integer, $fraction] = array_pad(explode('.', number_format(abs($value), $decimals, '.', ''), 2), 2, '');
        $grouped = ltrim(strrev(chunk_split(strrev($integer), 3, '.')), '.');
        $negative = $value < 0 && trim($integer.$fraction, '0') !== '';

        return ($negative ? '-' : '').$grouped.($decimals > 0 ? ','.$fraction : '');
    }

    /**
     * Girilen ya da kayitli tutari sayiya cevirir; cevrilemezse null.
     *
     * - int / float oldugu gibi.
     * - Virgul varsa Turkce yazim: noktalar binlik ayrac, virgul ondalik
     *   ("1.000,50" => 1000.5).
     * - Virgul yoksa ve metin yalniz uclu gruplardan olusuyorsa ("1.000",
     *   "12.500.000") noktalar binlik ayractir (giris maskesinin kurussuz
     *   metni). Aksi halde duz ondalik sayidir ("1000.50", "1000.5000":
     *   veritabani tutarlari 2 ya da 4 ondalik basamaklidir, uc basamakli
     *   ondalik tutar kolonu yoktur).
     */
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $value) ?? '';

        if ($text === '' || $text === '-') {
            return null;
        }

        if (str_contains($text, ',')) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $text) === 1) {
            $text = str_replace('.', '', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }
}
