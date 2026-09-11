<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum RetentionDisposition: string implements HasLabel
{
    use HasTranslatedLabel;

    case Purge = 'purge';
    case Anonymize = 'anonymize';
    case ColdArchive = 'cold_archive';
    case Review = 'review';
}
