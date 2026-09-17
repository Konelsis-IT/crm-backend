<?php

declare(strict_types=1);

namespace App\Query\Reference;

use App\Models\Reference\Country;
use App\Models\Reference\Currency;

/**
 * Kod anahtarli referans tablolarinin secim listeleri (ulke, para birimi).
 */
final class ReferenceOptions
{
    /**
     * Ulke listesi; anahtar ISO kodu, deger Turkce ad.
     *
     * @return array<string, string>
     */
    public function countries(): array
    {
        return Country::query()
            ->orderBy('name_tr')
            ->pluck('name_tr', 'code')
            ->all();
    }

    /**
     * Para birimi listesi; anahtar da deger de ISO kodu (TRY, USD, EUR) —
     * kullanici karari (10 Eylul 2026): girdilerde uzun ad gosterilmez.
     *
     * @return array<string, string>
     */
    public function currencies(): array
    {
        return Currency::query()
            ->orderBy('code')
            ->pluck('code', 'code')
            ->all();
    }

    /**
     * Is dosyasi ve teklifte kullanilan para birimleri (16 Eylul 2026
     * kullanici karari, D-101): yalniz config `konelsis.offer_currencies`
     * listesindekiler, veritabanindaki kod sirasiyla. Kesisim bos kalirsa
     * (liste tanimsiz ya da kodlar yuklenmemis) tum para birimleri doner.
     *
     * @return array<string, string>
     */
    public function offerCurrencies(): array
    {
        $all = $this->currencies();
        $allowed = array_map(
            static fn (mixed $code): string => strtoupper(trim((string) $code)),
            (array) config('konelsis.offer_currencies', []),
        );

        $filtered = array_intersect_key($all, array_flip($allowed));

        return $filtered === [] ? $all : $filtered;
    }
}
