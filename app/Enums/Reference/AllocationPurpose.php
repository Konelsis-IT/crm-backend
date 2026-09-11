<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum AllocationPurpose: string implements HasLabel
{
    use HasTranslatedLabel;

    case BusinessCase = 'business_case';
}
