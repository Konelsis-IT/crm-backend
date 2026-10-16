<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Procurement\CatalogNames;
use Closure;
use Filament\Forms\Components\Select;

/**
 * Iki satirli secim kutusunu kurar (D-192): secenekler TwoLineOption HTML'i
 * (ust satir ad, alt satir silik aciklama), arama ise duz metinde sunucuda
 * yapilir. Filament'in tarayici aramasi HTML etiketlerinde de arardi ("su"
 * yazinca her secenegin `kc-opt-sub` sinifi eslesirdi); bu yuzden aranabilir
 * iki satirli secim her zaman `getSearchResultsUsing` ile calisir.
 *
 * Kullanim: `Select::make('x')->tap(TwoLineSelect::rows(fn (Get $get): array => [...]))`.
 * Satir kapanisi Filament'in degerlendirmesiyle cagrilir (Get, $record...
 * enjekte edilir) ve kimlik => [ad, aciklama] dondurur.
 */
final class TwoLineSelect
{
    /** Arama sonucunda en cok bu kadar secenek. */
    public const SEARCH_LIMIT = 50;

    /**
     * @param  Closure  $rows  kimlik => [ad, aciklama|null]
     * @param  (Closure(array<int, int|string>): array<int|string, array{0: string, 1: string|null}>)|null  $labels
     *                                                                                                         Listede olmayan secili degerlerin satirlari (pasif kisi, arsivli kalem).
     * @return Closure(Select): void
     */
    public static function rows(Closure $rows, ?Closure $labels = null): Closure
    {
        return static function (Select $select) use ($rows, $labels): void {
            $resolve = static fn (): array => (array) $select->evaluate($rows);

            $select
                ->allowHtml()
                ->options(static fn (): array => TwoLineOption::options($resolve()))
                ->getSearchResultsUsing(static fn (string $search): array => TwoLineOption::options(self::search($resolve(), $search)))
                ->getOptionLabelUsing(static function (mixed $value) use ($resolve, $labels): ?string {
                    if (blank($value)) {
                        return null;
                    }

                    $value = FormState::value($value);
                    $row = $resolve()[$value] ?? ($labels !== null ? ($labels([$value])[$value] ?? null) : null);

                    return is_array($row) ? TwoLineOption::html((string) $row[0], $row[1] ?? null) : null;
                })
                ->getOptionLabelsUsing(static function (mixed $values) use ($resolve, $labels): array {
                    $values = array_values(array_filter(array_map(static fn (mixed $value): mixed => FormState::value($value), (array) $values), static fn (mixed $value): bool => filled($value)));
                    $all = $resolve();
                    $missing = array_values(array_filter($values, static fn (mixed $value): bool => ! array_key_exists($value, $all)));
                    $extra = ($missing !== [] && $labels !== null) ? $labels($missing) : [];
                    $result = [];

                    foreach ($values as $value) {
                        $row = $all[$value] ?? $extra[$value] ?? null;

                        if (is_array($row)) {
                            $result[$value] = TwoLineOption::html((string) $row[0], $row[1] ?? null);
                        }
                    }

                    return $result;
                });
        };
    }

    /**
     * Ad ya da aciklamasinda aranan metni geciren satirlar (Turkce harf ve
     * buyuk / kucuk harf farki gozetilmez).
     *
     * @param  array<int|string, array{0: string, 1: string|null}>  $rows
     * @return array<int|string, array{0: string, 1: string|null}>
     */
    public static function search(array $rows, string $search, int $limit = self::SEARCH_LIMIT): array
    {
        $needle = CatalogNames::normalize($search);

        if ($needle === '') {
            return array_slice($rows, 0, $limit, preserve_keys: true);
        }

        $found = [];

        foreach ($rows as $key => $row) {
            $haystack = CatalogNames::normalize(trim(((string) ($row[0] ?? '')).' '.((string) ($row[1] ?? ''))));

            if (str_contains($haystack, $needle)) {
                $found[$key] = $row;

                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }
}
