<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tekrarli isin sikligi (D-219, "Görev ve sorumluluklar"). Donem anahtari:
 * gunluk "2026-10-16", haftalik "2026-W42", aylik "2026-10". Gunluk is hafta
 * ici gunlerde acilir.
 */
enum RecurrenceFrequency: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function getColor(): string
    {
        return match ($this) {
            self::Daily => 'info',
            self::Weekly => 'warning',
            self::Monthly => 'violet',
        };
    }

    /** Gunun ait oldugu donemin anahtari. */
    public function periodKey(CarbonImmutable $day): string
    {
        return match ($this) {
            self::Daily => $day->format('Y-m-d'),
            self::Weekly => $day->format('o-\WW'),
            self::Monthly => $day->format('Y-m'),
        };
    }

    /** Donemin son gunu (isin son tarihi). */
    public function periodEnd(CarbonImmutable $day): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $day,
            self::Weekly => $day->endOfWeek()->startOfDay(),
            self::Monthly => $day->endOfMonth()->startOfDay(),
        };
    }

    /** Donemin kisa adi ("16.10.2026", "42. hafta", "Ekim 2026"). */
    public function periodLabel(CarbonImmutable $day): string
    {
        return match ($this) {
            self::Daily => $day->format('d.m.Y'),
            self::Weekly => __('recurring_work.period.week', ['week' => $day->isoWeek(), 'year' => $day->isoWeekYear()]),
            self::Monthly => $day->locale(app()->getLocale())->translatedFormat('F Y'),
        };
    }

    /**
     * Bugun bu siklikta yeni donem acilir mi: gunluk hafta ici her gun,
     * haftalik secilen gunde (yoksa pazartesi), aylik secilen gunde (yoksa
     * ayin ilk is gunu, ay sonunu asmaz).
     */
    public function opensOn(CarbonImmutable $day, ?int $weekday, ?int $monthDay): bool
    {
        return match ($this) {
            self::Daily => ! $day->isWeekend(),
            self::Weekly => $day->dayOfWeekIso === ($weekday ?? 1),
            self::Monthly => $monthDay !== null
                ? $day->day === min($monthDay, $day->daysInMonth)
                : $day->equalTo(self::firstWorkday($day)),
        };
    }

    /**
     * Gunun donemindeki acilis gunu (gunluk: hafta ici o gun, hafta sonu
     * null; haftalik: secilen gun; aylik: secilen gun ya da ilk is gunu).
     * Zamanlayici bir gun calismasa da donem kacmaz: acilis gunu gectiyse ve
     * donemin isi yoksa is acilir.
     */
    public function openingDay(CarbonImmutable $day, ?int $weekday, ?int $monthDay): ?CarbonImmutable
    {
        return match ($this) {
            self::Daily => $day->isWeekend() ? null : $day,
            self::Weekly => $day->startOfWeek()->addDays(max(1, min(7, $weekday ?? 1)) - 1),
            self::Monthly => $monthDay !== null
                ? $day->startOfMonth()->addDays(min(max(1, $monthDay), $day->daysInMonth) - 1)
                : self::firstWorkday($day),
        };
    }

    /**
     * Donemin isinin son tarihi: gun secildiyse o gun (rapor o gun beklenir),
     * secilmediyse donemin son gunu.
     */
    public function dueDay(CarbonImmutable $day, ?int $weekday, ?int $monthDay): CarbonImmutable
    {
        $explicit = match ($this) {
            self::Daily => true,
            self::Weekly => $weekday !== null,
            self::Monthly => $monthDay !== null,
        };

        return $explicit ? ($this->openingDay($day, $weekday, $monthDay) ?? $day) : $this->periodEnd($day);
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(DisplayTime::zone())->startOfDay();
    }

    private static function firstWorkday(CarbonImmutable $day): CarbonImmutable
    {
        $first = $day->startOfMonth();

        while ($first->isWeekend()) {
            $first = $first->addDay();
        }

        return $first;
    }
}
