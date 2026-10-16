<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\TurkishFold;
use Filament\Forms\Components\Select;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Secim kutularinda Turkce arama (D-214; kullanici 15 Ekim 2026: "İdris ile
 * idris aynıdır ... irmak İrmak yazılsa da arama doğru sonucu getirmelidir").
 *
 * Filament, secenekleri sayfaya gelen secim kutusunda aramayi tarayicida duz
 * kucuk harfle yapar; orada "irmak" "İrmak"i, "ırmak" "Irmak"i bulmaz. Bu
 * yuzden her secim kutusunun aramasi sunucuya gider ve secenekler Turkce
 * katlanarak suzulur. Iliskili (relationship) ya da kendi aramasini kuran
 * secim kutusu bu aramayi ezer; onlarin veritabani aramasi TurkishSearchGrammar
 * ile zaten Turkce duyarsizdir.
 */
final class TurkishSelectSearch
{
    /** Sunucu aramasinin bekleme suresi (ms); Filament varsayilani 1000. */
    public const DEBOUNCE = 400;

    public static function configure(Select $select): void
    {
        $select
            ->getSearchResultsUsing(static fn (Select $component, ?string $search): array => self::filter(
                $component->getOptions(),
                (string) $search,
                $component->getSearchableOptionFields(),
                $component->getOptionsLimit(),
            ))
            ->searchDebounce(static fn (Select $component): int => $component->hasRelationship() ? 1000 : self::DEBOUNCE);
    }

    /**
     * Secenekleri arama metniyle suzer; gruplar korunur.
     *
     * @param  array<array-key, mixed>  $options
     * @param  array<string>  $fields  label ve/veya value
     * @return array<array-key, mixed>
     */
    public static function filter(array $options, string $search, array $fields = ['label'], ?int $limit = null): array
    {
        $needle = TurkishFold::fold($search);

        if ($needle === '') {
            return $options;
        }

        $inLabel = in_array('label', $fields, true);
        $inValue = in_array('value', $fields, true);
        $results = [];

        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $group = self::filter($label, $search, $fields);

                if ($group !== []) {
                    $results[$value] = $group;
                }

                continue;
            }

            if (($inLabel && str_contains(self::text($label), $needle)) || ($inValue && str_contains(TurkishFold::fold((string) $value), $needle))) {
                $results[$value] = $label;
            }
        }

        return $limit !== null && $limit > 0 ? array_slice($results, 0, $limit, true) : $results;
    }

    /** Secenek etiketinin aranan metni (iki satirli HTML etiketlerde duz yazi). */
    private static function text(mixed $label): string
    {
        $label = $label instanceof Htmlable ? $label->toHtml() : (string) $label;

        return TurkishFold::fold(html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
