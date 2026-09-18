<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Medya satirinin kullanim yeri (B31): galeri ogesi ya da metin editorune
 * gomulen gorsel. Galeri = usage gallery VE removed_at bos.
 */
enum SocialMediaUsage: string implements HasLabel
{
    use HasTranslatedLabel;

    case Gallery = 'gallery';
    case Inline = 'inline';
}
