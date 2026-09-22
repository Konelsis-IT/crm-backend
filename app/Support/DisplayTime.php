<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Ekranda gosterilen saat (22 Eylul 2026 kullanici talimati: sistem saati
 * Istanbul'a gore). Kayitlar veritabaninda UTC tutulur (config app.timezone,
 * DB_TIMEZONE +00:00); ekrana, bildirimlere, Excel ve PDF'e kurum saatiyle
 * (konelsis.organization.default_timezone, varsayilan Europe/Istanbul) yazilir.
 * Filament tablolari, kayit alanlari ve saatli secicileri ayni saati
 * FilamentTimezone ile kullanir (AppServiceProvider).
 */
final class DisplayTime
{
    public static function zone(): string
    {
        $zone = config('konelsis.organization.default_timezone', 'Europe/Istanbul');

        return is_string($zone) && $zone !== '' ? $zone : 'Europe/Istanbul';
    }

    /** Kurum saatinde bicimlenmis deger; bos ise $empty. */
    public static function format(?CarbonInterface $value, string $format = 'd.m.Y H:i', string $empty = '-'): string
    {
        return $value === null ? $empty : $value->copy()->timezone(self::zone())->format($format);
    }
}
