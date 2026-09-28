<?php

declare(strict_types=1);

namespace App\Filament\Support;

use BackedEnum;

/**
 * Form durumunu karsilastirma icin duz degere indirir.
 *
 * Filament, secenekleri bir enum sinifindan gelen alanin (`->options(Enum::class)`)
 * degerini `$get()` ile enum nesnesi olarak verir; `$get('mode') ===
 * Enum::X->value` gibi bir karsilastirma bu yuzden hic tutmaz ve bagli alan
 * gizli kalir (25 Eylul 2026: dokuman olusturmada "Belgenin asli" alani hic
 * gorunmuyordu). Gorunurluk / zorunluluk kosullarinda deger once buradan gecer.
 */
final class FormState
{
    public static function value(mixed $state): ?string
    {
        if ($state instanceof BackedEnum) {
            return (string) $state->value;
        }

        if (is_string($state) || is_int($state)) {
            return (string) $state;
        }

        return null;
    }
}
