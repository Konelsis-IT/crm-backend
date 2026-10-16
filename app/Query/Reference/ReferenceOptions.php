<?php

declare(strict_types=1);

namespace App\Query\Reference;

use App\Models\Reference\Country;
use App\Models\Reference\Currency;
use App\Support\Money;

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
     * Secilebilen para birimleri; anahtar ISO kodu (kaydedilen deger
     * degismez), etiket simge + ad ("[TL simgesi] Turk lirasi", "lei Rumen
     * leyi"; D-180: ISO kodu ekranda gorulmez).
     *
     * Yalniz config `konelsis.currencies` listesi (TRY, USD, EUR, RON):
     * 16 Eylul 2026 kullanici karari (D-101) potansiyel is ve teklif icindi;
     * 15 Ekim 2026'da kullanici ("Ben sana para birimi ile ilgili sadece belli
     * para birimleri gorunecek demistim") butun ekranlara genisletti (D-213):
     * her para birimi secimi bu listeyi kullanir. Liste veritabanindaki kod
     * sirasiyla; kesisim bos kalirsa (liste tanimsiz ya da kodlar yuklenmemis)
     * tum para birimleri doner.
     *
     * @return array<string, string>
     */
    public function currencies(): array
    {
        $allowed = array_map(
            static fn (mixed $code): string => strtoupper(trim((string) $code)),
            (array) config('konelsis.currencies', []),
        );

        $all = Currency::query()
            ->orderBy('code')
            ->get(['code', 'name_tr'])
            ->mapWithKeys(static fn (Currency $currency): array => [
                (string) $currency->code => Money::label((string) $currency->code, $currency->name_tr),
            ])
            ->all();

        $filtered = array_intersect_key($all, array_flip($allowed));

        return $filtered === [] ? $all : $filtered;
    }
}
