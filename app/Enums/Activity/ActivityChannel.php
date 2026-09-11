<?php

declare(strict_types=1);

namespace App\Enums\Activity;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Hareketin hangi yoldan yapildigi.
 */
enum ActivityChannel: string implements HasLabel
{
    use HasTranslatedLabel;

    case Panel = 'panel';
    case Scheduled = 'scheduled';
    case Integration = 'integration';
    case Import = 'import';
}
