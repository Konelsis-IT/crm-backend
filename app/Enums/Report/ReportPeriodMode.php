<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * Raporun donem bicimi (D-86): yok, gun, hafta (Pzt-Paz), ay, serbest aralik.
 * Gun/hafta/ay tek bir tarih secimiyle girilir; servis donemi normalize eder.
 */
enum ReportPeriodMode: string implements HasLabel
{
    use HasTranslatedLabel;

    case None = 'none';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Range = 'range';

    /** Tek tarih seciminden turetilen donemler. */
    public function isCalendarUnit(): bool
    {
        return in_array($this, [self::Day, self::Week, self::Month], true);
    }

    /**
     * Secilen gunun donemi (ikisi de dahil, gun basi): gun = o gun, hafta =
     * Pazartesi-Pazar, ay = ayin tamami. Rapor servisi donemi bu kuralla
     * kaydeder; gunluk / haftalik rapor onerileri (D-167) ayni araligi okur.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function bounds(Carbon $date): array
    {
        $day = $date->copy()->startOfDay();

        return match ($this) {
            self::Week => [$day->copy()->startOfWeek(Carbon::MONDAY), $day->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()],
            self::Month => [$day->copy()->startOfMonth(), $day->copy()->endOfMonth()->startOfDay()],
            default => [$day, $day->copy()],
        };
    }
}
