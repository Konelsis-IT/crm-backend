<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum LegalEntityStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Inactive = 'inactive';
    case Dissolved = 'dissolved';
}
