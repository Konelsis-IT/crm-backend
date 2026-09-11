<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum AllocationStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Assigned = 'assigned';
    case Void = 'void';
}
