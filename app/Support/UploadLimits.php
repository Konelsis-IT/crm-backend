<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dosya yukleme sinirlari (D-127): belge alanlari 1 GB'a kadar, diger
 * alanlar (fotograf, gorsel, sohbet) kendi sinirlarini tasir.
 * Ayar: config/konelsis.php `uploads`.
 */
final class UploadLimits
{
    /** Belge / dokuman alanlarinin siniri (KB). */
    public static function documentMaxKb(): int
    {
        return max(1, (int) config('konelsis.uploads.document_max_kb', 1048576));
    }

    /** Gecici yukleme imzasinin gecerlilik suresi (dk). */
    public static function uploadMinutes(): int
    {
        return max(5, (int) config('konelsis.uploads.max_upload_minutes', 120));
    }

    /** Kucuk gorsel / olcu uretilecek en buyuk gorsel (bayt). */
    public static function imageProcessingMaxBytes(): int
    {
        return max(1, (int) config('konelsis.uploads.image_processing_max_kb', 51200)) * 1024;
    }

    /** Insan okunur boyut ("1 GB", "20 MB"). */
    public static function readable(int $kilobytes): string
    {
        if ($kilobytes >= 1048576 && $kilobytes % 1048576 === 0) {
            return ($kilobytes / 1048576).' GB';
        }

        return (string) round($kilobytes / 1024).' MB';
    }
}
