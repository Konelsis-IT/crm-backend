<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Gorsel bicimi (B31, D-106): ozgun, kare, dikey, hikaye, yatay.
 * Bicim secildiginde gorsel hedef olcuye "kapla ve kirp" ile getirilir.
 */
enum SocialImageFormat: string implements HasLabel
{
    use HasTranslatedLabel;

    case Original = 'original';
    case Square = 'square';
    case Portrait = 'portrait';
    case Story = 'story';
    case Landscape = 'landscape';

    /**
     * Hedef olcu (piksel); ozgun bicimde null. Dizi hem sirali hem adli
     * okunabilir: `[$w, $h] = $format->targetSize()` ve
     * `$format->targetSize()['width']` ayni sonucu verir.
     *
     * @return array{0: int, 1: int, width: int, height: int}|null
     */
    public function targetSize(): ?array
    {
        $size = match ($this) {
            self::Original => null,
            self::Square => [1080, 1080],
            self::Portrait => [1080, 1350],
            self::Story => [1080, 1920],
            self::Landscape => [1920, 1080],
        };

        if ($size === null) {
            return null;
        }

        return [0 => $size[0], 1 => $size[1], 'width' => $size[0], 'height' => $size[1]];
    }

    public function width(): ?int
    {
        return $this->targetSize()['width'] ?? null;
    }

    public function height(): ?int
    {
        return $this->targetSize()['height'] ?? null;
    }

    /** Genislik / yukseklik orani; ozgun bicimde null. */
    public function aspectRatio(): ?float
    {
        $size = $this->targetSize();

        return $size === null ? null : $size['width'] / $size['height'];
    }

    /** Kirpma gerektiren bicim mi (ozgun disindakiler)? */
    public function requiresCrop(): bool
    {
        return $this !== self::Original;
    }

    /**
     * Arayuzde secilebilen kirpma bicimleri (ozgun haric).
     *
     * @return list<self>
     */
    public static function croppable(): array
    {
        return [self::Square, self::Portrait, self::Story, self::Landscape];
    }
}
