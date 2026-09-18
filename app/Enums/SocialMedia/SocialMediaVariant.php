<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Medya satirinin surum turu (B31). Kok satir `original`dir; turetilen satir
 * bir bicim uygulanmissa o bicimin adini, yalniz cozunurluk degismisse
 * `resized` degerini tasir.
 */
enum SocialMediaVariant: string implements HasLabel
{
    use HasTranslatedLabel;

    case Original = 'original';
    case Square = 'square';
    case Portrait = 'portrait';
    case Story = 'story';
    case Landscape = 'landscape';
    case Resized = 'resized';

    /** Turetilen satirin surum turu: bicim uygulandiysa o bicim, degilse `resized`. */
    public static function forFormat(?SocialImageFormat $format): self
    {
        if ($format === null || $format === SocialImageFormat::Original) {
            return self::Resized;
        }

        return self::from($format->value);
    }

    /** Surumun tasidigi kirpma bicimi; ozgun ve yeniden boyutlanmis satirda null. */
    public function imageFormat(): ?SocialImageFormat
    {
        return match ($this) {
            self::Original, self::Resized => null,
            default => SocialImageFormat::from($this->value),
        };
    }

    public function isOriginal(): bool
    {
        return $this === self::Original;
    }
}
