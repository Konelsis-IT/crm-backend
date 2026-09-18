<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** Kendi hesabimizin turu (B31, D-106): kurumsal hesap ya da yonetici hesabi. */
enum SocialProfileKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case Corporate = 'corporate';
    case Executive = 'executive';
}
