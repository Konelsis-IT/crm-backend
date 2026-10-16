<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

/**
 * Acil uyari kurali (D-207; kullanici 14 Ekim 2026: "Ekranda bir anda beliren
 * carpi ile kapatilan acil yapilacak islem bildirilir, belirli periyotlarla
 * ekranda yeniden cikar. Isin acilligi ve surenin sikismasina gore degisir").
 *
 * Seviye kalan gune ve "acil" isaretine gore belirlenir; kapatilan uyari
 * seviyesinin araliginda yeniden gosterilir:
 *  - kritik: suresi gecmis ya da 1 gunden az -> 15 dakikada bir;
 *  - yuksek: 3 gunden az, ya da acil isaretli (tarihsiz) -> saatte bir;
 *  - orta: 7 gunden az, ya da acil isaretli ama suresi uzak -> 4 saatte bir.
 * Isaretsiz ve 7 gunden uzak is acil degildir.
 */
final class UrgencyRule
{
    public const CRITICAL = 'critical';

    public const HIGH = 'high';

    public const MEDIUM = 'medium';

    /** Kapatilan uyarinin yeniden gosterilme araligi (dakika). */
    public const INTERVALS = [
        self::CRITICAL => 15,
        self::HIGH => 60,
        self::MEDIUM => 240,
    ];

    /** Siralama: kritik once. */
    public const ORDER = [self::CRITICAL => 0, self::HIGH => 1, self::MEDIUM => 2];

    public static function level(?int $daysLeft, bool $flagged): ?string
    {
        if ($daysLeft !== null) {
            return match (true) {
                $daysLeft <= 1 => self::CRITICAL,
                $daysLeft <= 3 => self::HIGH,
                $daysLeft <= 7 => $flagged ? self::HIGH : self::MEDIUM,
                $flagged => self::MEDIUM,
                default => null,
            };
        }

        return $flagged ? self::HIGH : null;
    }

    public static function interval(string $level): int
    {
        return self::INTERVALS[$level] ?? self::INTERVALS[self::MEDIUM];
    }
}
