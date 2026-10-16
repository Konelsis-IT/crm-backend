<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turkce harf katlama (D-214). Arama ve eslestirmede "İdris = idris",
 * "Irmak = ırmak", "Güneş = gunes": kucuk harf, I/ı/İ/i ayni, Turkce
 * harfler duz karsiligina iner. Veritabani aramasi ayni esitligi
 * utf8mb4_general_ci ile kurar (TurkishSearchGrammar).
 */
final class TurkishFold
{
    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        // "I" harfinin kucugu birlesik nokta birakir (i + U+0307); katlamada atilir.
        $text = str_replace("\u{0307}", '', $text);

        return strtr($text, ['ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
    }
}
