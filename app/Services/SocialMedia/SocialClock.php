<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\SocialMedia\SocialReminderStage;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Sosyal Medya modulunde "bugun"un TEK kaynagi (B31, D-106).
 *
 * Uygulama saati UTC'dir; plan tarihi ise takvim gunudur ve kurumun saat
 * dilimine (konelsis.organization.default_timezone) gore yorumlanir. Sunum
 * katmanindaki asama, sayaclar, asama suzgeci, pano listesi, takvim, analiz,
 * acil onay penceresi ve hatirlatma taramasi "bugun"u yalniz buradan alir;
 * baska hicbir yerde now()/today() ile gun hesaplanmaz.
 *
 * Gun farki anlar arasi degil TAKVIM GUNLERI arasi hesaplanir: `planned_on`
 * UTC gece yarisi olarak okunur, "bugun" ise kurum saatinde gece yarisidir;
 * ikisini an olarak karsilastirmak saat farki kadar kayar.
 */
final class SocialClock
{
    /** Kurumun saat dilimi. */
    public static function timezone(): string
    {
        $timezone = config('konelsis.organization.default_timezone', 'Europe/Istanbul');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Europe/Istanbul';
    }

    /** Kurum saatine gore bugunun baslangici. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }

    /** Bugunun `Y-m-d` yazimi (tarih kolonlariyla karsilastirmak icin). */
    public static function todayString(): string
    {
        return self::today()->format('Y-m-d');
    }

    /**
     * Bir takvim gununu kurum saatinde gun baslangicina cevirir. Deger tarih
     * nesnesiyse yalniz yil-ay-gun kismi alinir (saat dilimi kaymasi olmaz).
     */
    public static function date(DateTimeInterface|string $date): CarbonImmutable
    {
        $day = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr(trim($date), 0, 10);

        return CarbonImmutable::parse($day, self::timezone())->startOfDay();
    }

    /** Bugunden verilen gune kalan takvim gunu sayisi (gecmis icin negatif). */
    public static function daysUntil(DateTimeInterface|string $date): int
    {
        return (int) round(self::today()->diffInDays(self::date($date), false));
    }

    /**
     * Plan tarihinin asamasi: gecikti, bugun, yarin, yaklasiyor ya da null.
     * Yalniz tarihe bakar; durum ve paylasim kosullarini cagiran denetler.
     */
    public static function stageFor(DateTimeInterface|string|null $plannedOn): ?SocialReminderStage
    {
        if ($plannedOn === null || $plannedOn === '') {
            return null;
        }

        return SocialReminderStage::forDays(self::daysUntil($plannedOn), self::approachingDays());
    }

    /** "Yaklasiyor" penceresi (gun). */
    public static function approachingDays(): int
    {
        return max(2, (int) config('konelsis.social_media.approaching_days', 3));
    }

    /** Acil onay penceresi (gun): plan tarihi bugun + N gun icindeyse istenebilir. */
    public static function urgentWindowDays(): int
    {
        return max(0, (int) config('konelsis.social_media.urgent_window_days', 3));
    }

    /** Plan tarihi acil onay penceresinde mi (gecmis tarih dahil)? */
    public static function isWithinUrgentWindow(DateTimeInterface|string|null $plannedOn): bool
    {
        if ($plannedOn === null || $plannedOn === '') {
            return false;
        }

        return self::daysUntil($plannedOn) <= self::urgentWindowDays();
    }
}
