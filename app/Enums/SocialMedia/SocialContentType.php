<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Icerik turu (B31, D-106). Tur, icerik olusturulduktan sonra degismez.
 * Fotograf ve video galeri tasir; kisa metin duz govde (`body_text`), uzun
 * metin ve blog zengin govde (`body_html`) tasir.
 */
enum SocialContentType: string implements HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Photo = 'photo';
    case Video = 'video';
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Blog = 'blog';

    /** Galerisi (gorsel/video dosyalari) olan turler. */
    public function hasGallery(): bool
    {
        return in_array($this, [self::Photo, self::Video], true);
    }

    /** Zengin govde (`body_html`) tasiyan turler. */
    public function hasRichBody(): bool
    {
        return in_array($this, [self::LongText, self::Blog], true);
    }

    /** Duz govde (`body_text`) tasiyan tur. */
    public function hasPlainBody(): bool
    {
        return $this === self::ShortText;
    }

    /** Metin turu mu (onay icin bos olmayan govde ister)? */
    public function isText(): bool
    {
        return $this->hasRichBody() || $this->hasPlainBody();
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Photo => Heroicon::OutlinedPhoto,
            self::Video => Heroicon::OutlinedVideoCamera,
            self::ShortText => Heroicon::OutlinedChatBubbleBottomCenterText,
            self::LongText => Heroicon::OutlinedDocumentText,
            self::Blog => Heroicon::OutlinedNewspaper,
        };
    }
}
