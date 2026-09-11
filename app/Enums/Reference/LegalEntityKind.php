<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum LegalEntityKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case Parent = 'parent';
    case Subsidiary = 'subsidiary';
    case Branch = 'branch';
    case Spv = 'spv';
    case Jv = 'jv';
}
