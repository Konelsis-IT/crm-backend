<?php

declare(strict_types=1);

namespace App\Enums\Activity;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum RegistryStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Retired = 'retired';
}
