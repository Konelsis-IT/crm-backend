<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\SocialMedia\SocialPlatform;

/**
 * Sosyal medya platform isareti (Genel bakis, D-146): Sosyal Medya ekraninin
 * kullandigi ayni cizgi simgeler (resources/js/social/social-core.js ICONS),
 * marka renginde, tablo gorsel sutununa verilebilen kucuk bir resim olarak.
 */
final class PlatformLogo
{
    /** 24x24 cizgi yollari; social-core.js ile ayni cizim. */
    private const PATHS = [
        'instagram' => 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zM16 11.4a4 4 0 1 1-7.9 1.2 4 4 0 0 1 7.9-1.2zM17.5 6.5h.01',
        'facebook' => 'M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z',
        'linkedin' => 'M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2zM4 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4z',
        'x' => 'M4 3.5h4.2l11.8 17h-4.2zM19.5 3.5l-6.2 7.1M10.7 13.4l-6.2 7.1',
        'youtube' => 'M22.5 6.4a2.8 2.8 0 0 0-1.9-2C18.9 4 12 4 12 4s-6.9 0-8.6.5a2.8 2.8 0 0 0-1.9 2A29 29 0 0 0 1 11.8a29 29 0 0 0 .5 5.3 2.8 2.8 0 0 0 1.9 1.9c1.7.5 8.6.5 8.6.5s6.9 0 8.6-.5a2.8 2.8 0 0 0 1.9-1.9 29 29 0 0 0 .5-5.3 29 29 0 0 0-.5-5.4zM9.75 15l5.75-3.2-5.75-3.3z',
        'tiktok' => 'M14 3v12.5a4 4 0 1 1-4-4M14 3c.4 3 2.4 5 5.5 5.3',
        'website' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z',
    ];

    public static function url(?SocialPlatform $platform): string
    {
        $platform ??= SocialPlatform::Website;
        $path = self::PATHS[$platform->value] ?? self::PATHS['website'];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="'.$platform->brandColor()
            .'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="'.$path.'"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
