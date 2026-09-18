<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Icerigin paylasildigi ve hesaplarin izlendigi platformlar (B31, D-106).
 * Etiket marka adidir; yalniz `website` cevrilir.
 */
enum SocialPlatform: string implements HasLabel
{
    use HasTranslatedLabel;

    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case Website = 'website';

    /**
     * Marka rengi (hex). Arayuzde yalniz SVG grafik serilerinde kullanilir;
     * dugme ve rozetler CSS degiskeninden (`--ks-platform-<deger>`) beslenir.
     * X siyahtir (istemci #000000 degerini metin rengine cevirir); TikTok
     * grafikte X'ten ayrilabilsin diye markanin kirmizi tonunu tasir.
     */
    public function brandColor(): string
    {
        return match ($this) {
            self::Instagram => '#E1306C',
            self::Facebook => '#1877F2',
            self::LinkedIn => '#0A66C2',
            self::X => '#000000',
            self::YouTube => '#FF0000',
            self::TikTok => '#FE2C55',
            self::Website => '#57534E',
        };
    }
}
