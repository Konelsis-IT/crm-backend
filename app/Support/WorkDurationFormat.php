<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Is panosu sure metinleri (B36, D-115): "5 gün", "3 saat", "3,5",
 * termin sapmasi ("+3 gün", "zamanında", "−1 gün", "termin 12.09 aşıldı").
 */
final class WorkDurationFormat
{
    /** Saniye -> "N gün" (bir gunden kisa ise "N saat"); sifirsa null. */
    public static function span(int $seconds): ?string
    {
        if ($seconds <= 0) {
            return null;
        }

        if ($seconds < 86400) {
            return __('work_item.values.hours_span', ['count' => max(1, (int) round($seconds / 3600))]);
        }

        return __('work_item.values.days', ['count' => (int) round($seconds / 86400)]);
    }

    public static function days(int|float|null $days): ?string
    {
        if ($days === null) {
            return null;
        }

        $formatted = is_float($days) && floor($days) !== $days
            ? number_format($days, 1, ',', '.')
            : (string) (int) $days;

        return __('work_item.values.days', ['count' => $formatted]);
    }

    public static function hours(int|float|null $hours): ?string
    {
        if ($hours === null || (float) $hours <= 0) {
            return null;
        }

        return number_format((float) $hours, 1, ',', '.');
    }

    /**
     * Termin sapmasi metni ve rengi.
     *
     * @return array{0: string|null, 1: string}
     */
    public static function deviation(?int $deviation, bool $closed, ?CarbonInterface $due): array
    {
        if ($deviation === null || $due === null) {
            return [null, 'gray'];
        }

        if (! $closed) {
            return $deviation > 0
                ? [__('work_item.values.due_passed', ['date' => $due->format('d.m')]), 'danger']
                : [__('work_item.values.due_on', ['date' => $due->format('d.m')]), 'gray'];
        }

        return match (true) {
            $deviation > 0 => ['+'.__('work_item.values.days', ['count' => $deviation]), 'danger'],
            $deviation === 0 => [__('work_item.values.on_time'), 'success'],
            default => ['−'.__('work_item.values.days', ['count' => abs($deviation)]), 'success'],
        };
    }
}
