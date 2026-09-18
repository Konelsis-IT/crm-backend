<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Cozunurluk dugmeleri (B31, D-106): SD, HD, Full HD uzun kenari sabit bir
 * degere getirir (buyutme serbest); "2x buyutme" iki katina cikarir ve uzun
 * kenar MAX_EDGE ile sinirlanir. Islem yeniden ornekleme (interpolasyon)dir.
 */
enum SocialResolutionPreset: string implements HasLabel
{
    use HasTranslatedLabel;

    /** Buyutmede uzun kenarin ust siniri (piksel). */
    public const MAX_EDGE = 4096;

    case Sd = 'sd';
    case Hd = 'hd';
    case FullHd = 'full_hd';
    case Double = 'double';

    /** Hedef uzun kenar (piksel); `double` icin null (katsayi ile calisir). */
    public function longEdge(): ?int
    {
        return match ($this) {
            self::Sd => 720,
            self::Hd => 1280,
            self::FullHd => 1920,
            self::Double => null,
        };
    }

    /** Olcek katsayisi; yalniz `double` icin 2.0, digerlerinde null. */
    public function factor(): ?float
    {
        return $this === self::Double ? 2.0 : null;
    }
}
