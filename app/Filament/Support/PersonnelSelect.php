<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Query\Personnel\PersonnelQueries;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

/**
 * Personel secim kutulari iki satirlidir (D-192, kullanici: "Ayni input
 * mantigini personel input'lari icinde yapalim. Personel ismi / Departman
 * ismi (aciklama gibi silik)"). Yalniz formlardaki secimler icindir; tablo
 * suzgecleri (SelectFilter), bilgi listeleri ve disa aktarimlar duz metin
 * kalir. Gizli hesap kapsami korunur (sorgular Personnel::query() ile).
 *
 * - `->tap(PersonnelSelect::all())`: butun personel.
 * - `->tap(PersonnelSelect::active())`: yalniz aktif personel.
 * - `->tap(PersonnelSelect::excluding(fn (?Personnel $record): ?int => ...))`: verilen kisiler haric (amir secimi).
 * - `->tap(PersonnelSelect::rows(fn (): array => [id => ad, ...]))`: ozel suzulmus liste.
 * - `->relationship('owner', 'full_name')->tap(PersonnelSelect::relationship())`: iliski secimi.
 */
final class PersonnelSelect
{
    /** @return Closure(Select): void */
    public static function all(): Closure
    {
        return self::people(activeOnly: false);
    }

    /** @return Closure(Select): void */
    public static function active(): Closure
    {
        return self::people(activeOnly: true);
    }

    /**
     * Verilen kisi(ler) haric; kapanis Filament degerlendirmesiyle cagrilir
     * ($record, Get enjekte edilir) ve kimlik ya da kimlik listesi dondurur.
     *
     * @return Closure(Select): void
     */
    public static function excluding(Closure | int | null $excluding): Closure
    {
        return self::people(activeOnly: false, excluding: $excluding);
    }

    /**
     * Hazir bir [kimlik => ad] listesi (ozel kurallarla suzulmus); departman
     * satiri eklenir.
     *
     * @return Closure(Select): void
     */
    public static function rows(Closure $options): Closure
    {
        return static function (Select $select) use ($options): void {
            TwoLineSelect::rows(
                static fn (): array => app(PersonnelQueries::class)->withDepartments((array) $select->evaluate($options)),
                static fn (array $ids): array => app(PersonnelQueries::class)->personnelRowsFor($ids),
            )($select);
        };
    }

    /**
     * `->relationship(..., 'full_name')` secimleri: arama Filament'in iliski
     * sorgusunda (ad soyad) kalir, yalniz etiket iki satirli olur.
     *
     * @return Closure(Select): void
     */
    public static function relationship(): Closure
    {
        return static function (Select $select): void {
            $select
                ->allowHtml()
                ->getOptionLabelFromRecordUsing(static fn (Model $record): string => self::label($record));
        };
    }

    /** Kisinin iki satirli etiketi (ad soyad / departman). */
    public static function label(Model $person): string
    {
        return TwoLineOption::html(
            (string) $person->getAttribute('full_name'),
            app(PersonnelQueries::class)->orgUnitName($person->getAttribute('org_unit_id')),
        );
    }

    /** @return Closure(Select): void */
    private static function people(bool $activeOnly, Closure | int | null $excluding = null): Closure
    {
        return static function (Select $select) use ($activeOnly, $excluding): void {
            TwoLineSelect::rows(
                static fn (): array => app(PersonnelQueries::class)->personnelRows($activeOnly, self::ids($select->evaluate($excluding))),
                static fn (array $ids): array => app(PersonnelQueries::class)->personnelRowsFor($ids),
            )($select);
        };
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(static fn (mixed $id): int => (int) $id, array_filter($values, static fn (mixed $id): bool => filled($id)))));
    }
}
