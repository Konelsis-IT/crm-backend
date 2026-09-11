<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Teknik kodlari arayuzde gosterilecek Turkce metne cevirir.
 * Ceviri bulunamazsa alt tire yerine bosluklu okunur metin uretir.
 */
final class ActivityLabels
{
    public static function action(?string $code): string
    {
        if ($code === null || $code === '') {
            return '-';
        }

        // Islem kodlari noktalidir ("approval_request.created"); __() noktayi
        // ic ice dizi sayip bulamiyordu ve Ingilizce "Approval request created"
        // uretiyordu (11 Eylul 2026 duzeltmesi). Dizi bir kez cekilir,
        // anahtar dogrudan okunur.
        $actions = __('activity.actions');

        if (is_array($actions) && isset($actions[$code]) && is_string($actions[$code])) {
            return $actions[$code];
        }

        return self::humanize($code);
    }

    public static function subject(?string $type): string
    {
        if ($type === null || $type === '') {
            return '-';
        }

        $key = "activity.subjects.{$type}";
        $translated = __($key);

        return $translated === $key ? self::humanize($type) : $translated;
    }

    public static function field(?string $name): string
    {
        if ($name === null || $name === '') {
            return '-';
        }

        $key = "activity.fields.{$name}";
        $translated = __($key);

        return $translated === $key ? self::humanize($name) : $translated;
    }

    /** Teknik degeri (alt tireli/noktali) okunur hale getirir. */
    public static function humanize(string $value): string
    {
        return Str::of($value)
            ->replace(['.', '_'], ' ')
            ->squish()
            ->ucfirst()
            ->value();
    }

    /**
     * Degisiklik ozetini insan okunur satirlara cevirir.
     *
     * @param  array<string, mixed>|null  $changes
     * @return list<string>
     */
    public static function changeLines(?array $changes): array
    {
        if ($changes === null || $changes === []) {
            return [];
        }

        $lines = [];

        foreach ($changes as $field => $value) {
            $label = self::field((string) $field);

            if (is_array($value) && array_key_exists('onceki', $value) && array_key_exists('yeni', $value)) {
                $lines[] = sprintf(
                    '%s: %s -> %s',
                    $label,
                    self::scalar($value['onceki']),
                    self::scalar($value['yeni']),
                );

                continue;
            }

            if (is_array($value)) {
                $parts = [];

                foreach ($value as $key => $item) {
                    $parts[] = self::field((string) $key).': '.self::scalar($item);
                }

                $lines[] = $label.' ('.implode(', ', $parts).')';

                continue;
            }

            $lines[] = $label.': '.self::scalar($value);
        }

        return $lines;
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '-',
            is_bool($value) => $value ? __('activity.values.yes') : __('activity.values.no'),
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
