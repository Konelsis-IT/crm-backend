<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Support\Money;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/**
 * MoneyInput'un durum donusumu (D-180). Livewire'daki ham durum ekranda
 * gorunen Turkce metindir ("1.000,50"); okunan durum (`$get()`, kayit,
 * dogrulama sonrasi veri) duz sayidir (1000.5).
 *
 * - set(): kayittan / `$set()`ten gelen deger ("1000.5000", 1000.5) maskenin
 *   metnine cevrilir.
 * - get(): metin sayiya cevrilir; bos ise null.
 */
final class MoneyStateCast implements StateCast
{
    public function __construct(
        private readonly int $decimals = 2,
    ) {}

    public function get(mixed $state): ?float
    {
        return Money::parse($state);
    }

    public function set(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        // D-185: alan kurusu her zaman sabit basamakla gosterir ("1.000,00").
        // Cevrilemeyen metin oldugu gibi kalir; dogrulama hatayi gosterir.
        return Money::fixed($state, $this->decimals) ?? (is_scalar($state) ? (string) $state : null);
    }
}
