<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** Medya satirinin turu (B31): gorsel ya da video. */
enum SocialMediaKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case Image = 'image';
    case Video = 'video';
}
