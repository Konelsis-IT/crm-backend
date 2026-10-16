<?php

declare(strict_types=1);

namespace App\Support\Procurement;

/**
 * Katalog adlarinin eslesme anahtari (D-187 / D-189 kurali: "zaten var olan
 * urunlerle eslestireceksin, her seferinde yeni kayit olusturmayacaksin").
 *
 * normalize(): buyuk / kucuk harf, Turkce harfler (I/i, s/s, g/g...), bosluk
 * ve noktalama farki gozetilmez: "30 Tonluk Kırıcı  Ekskavatör-Kira" ile
 * "30 tonluk kirici ekskavator kira" ayni anahtardir.
 *
 * similarity(): 0-100. Icindeki sayilar farkli adlar (2x10 / 4x10 NAYY,
 * 30 / 50 tonluk) benzer sayilmaz; bunlar farkli urunlerdir.
 */
final class CatalogNames
{
    /** Bu puandan itibaren "Benzer?" diye secime sunulur. */
    public const SIMILAR_THRESHOLD = 88;

    public static function normalize(string $name): string
    {
        $value = str_replace(['I', 'İ'], ['ı', 'i'], trim($name));
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            'ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u',
            'ë' => 'e', 'é' => 'e', 'ø' => 'o',
        ]);
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $value)), 0, 300);
    }

    /** Iki normalize ad arasindaki benzerlik (0-100). */
    public static function similarity(string $left, string $right): int
    {
        if ($left === '' || $right === '') {
            return 0;
        }

        if ($left === $right) {
            return 100;
        }

        if (self::numbers($left) !== self::numbers($right)) {
            return 0;
        }

        similar_text($left, $right, $percent);

        return (int) round($percent);
    }

    /**
     * @return list<string>
     */
    private static function numbers(string $value): array
    {
        preg_match_all('/\d+/', $value, $matches);

        return $matches[0];
    }
}
