<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Money;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Tutar ve para birimi gosteren tablo sutunu / kayit alani (D-180). Bicim
 * Money::format: "1.000,50" + para biriminin simgesi; ISO kodu ("TRY")
 * ekranda hic yazilmaz.
 *
 * $currency: kayittaki para birimi alaninin yolu (iliski noktali olabilir:
 * "contractVersion.currency_code") ya da kaydi alip kodu donduren closure.
 */
final class MoneyDisplay
{
    public static function column(string $name, string | Closure $currency = 'currency_code', int $maxDecimals = 2): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(static fn (mixed $state, ?Model $record): string => Money::format($state, self::currencyOf($record, $currency), '-', $maxDecimals))
            ->alignEnd();
    }

    public static function entry(string $name, string | Closure $currency = 'currency_code', int $maxDecimals = 2): TextEntry
    {
        return TextEntry::make($name)
            ->formatStateUsing(static fn (mixed $state, ?Model $record): string => Money::format($state, self::currencyOf($record, $currency), '-', $maxDecimals));
    }

    /** Para birimi sutunu: "[simge] Turk lirasi" (kod degil). */
    public static function currencyColumn(string $name = 'currency_code'): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(static fn (mixed $state): string => Money::label($state));
    }

    /** Para birimi kayit alani: "[simge] Turk lirasi" (kod degil). */
    public static function currencyEntry(string $name = 'currency_code'): TextEntry
    {
        return TextEntry::make($name)
            ->formatStateUsing(static fn (mixed $state): string => Money::label($state));
    }

    private static function currencyOf(?Model $record, string | Closure $currency): mixed
    {
        if ($currency instanceof Closure) {
            return $currency($record);
        }

        return $record === null ? null : data_get($record, $currency);
    }
}
