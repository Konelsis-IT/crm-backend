<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** Istatistik kaydinin kaynagi (B31): elle giris ya da yuklenen rapor. */
enum SocialMetricSource: string implements HasLabel
{
    use HasTranslatedLabel;

    case Manual = 'manual';
    case Upload = 'upload';
}
