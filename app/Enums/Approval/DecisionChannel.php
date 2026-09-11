<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum DecisionChannel: string implements HasLabel
{
    use HasTranslatedLabel;

    case Ui = 'ui';
    case Api = 'api';
    case ExternalService = 'external_service';
}
