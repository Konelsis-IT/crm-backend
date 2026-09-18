<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** Icerige verilen tepki (B31); `none` tepkinin geri alindigini soyler. */
enum SocialReactionType: string implements HasLabel
{
    use HasTranslatedLabel;

    case Like = 'like';
    case Dislike = 'dislike';
    case None = 'none';
}
