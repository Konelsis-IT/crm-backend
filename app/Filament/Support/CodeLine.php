<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Tablolarda kod ayri sutun degil, basligin altinda kucuk yaziyla durur
 * (kullanici 14 Ekim 2026: "Tablolarda kod alani gereksiz alan kapliyor
 * kod'lari daha kucuk font ile baslik altinda tutalim. Aciklama gibi ama
 * kucuk font ile ... Standart tum tablolar bu mantikla gitsin"). Ornekler:
 * teklif basliginin altinda TKLF kodu, Potansiyel is sutununda adin altinda
 * POTIS kodu, taraflarda kisa adin altinda "kod | uzun ad".
 *
 * Kodun yanindaki ek bilgi (uzun ad, taslak notu) " | " ile eklenir. Kod
 * aranabilsin diye cagiran baslik sutununun searchable() listesine kod
 * alanini ekler (ornek: ->searchable(['title', 'proposal_no'])).
 */
final class CodeLine
{
    /** Aciklama satirini kucuk yazan sinif (konelsis.css). */
    public const CSS_CLASS = 'kc-code-line';

    public const SEPARATOR = ' | ';

    /**
     * @param  string|Closure(Model): mixed  $code  kaydin alani (iliski yolu olabilir: 'businessCase.code') ya da kodu donduren kapanis
     * @param  (Closure(Model): mixed)|null  $extra  kodun yanindaki ek bilgi (uzun ad, taslak notu)
     */
    public static function under(TextColumn $column, string | Closure $code, ?Closure $extra = null): TextColumn
    {
        return $column
            ->description(fn (mixed $record): ?string => $record instanceof Model ? self::line($record, $code, $extra) : null)
            ->extraAttributes(['class' => self::CSS_CLASS], merge: true);
    }

    /**
     * Basligin altindaki satir: kod ve ek bilgi, bos olanlar atlanir.
     *
     * @param  string|Closure(Model): mixed  $code
     * @param  (Closure(Model): mixed)|null  $extra
     */
    public static function line(Model $record, string | Closure $code, ?Closure $extra = null): ?string
    {
        $parts = array_filter(
            array_map(self::text(...), [
                $code instanceof Closure ? $code($record) : data_get($record, $code),
                $extra instanceof Closure ? $extra($record) : null,
            ]),
            fn (?string $part): bool => $part !== null,
        );

        return $parts === [] ? null : implode(self::SEPARATOR, $parts);
    }

    private static function text(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
