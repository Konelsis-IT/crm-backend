<?php

declare(strict_types=1);

namespace App\Enums\Chat;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum ConversationStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Archived = 'archived';
    case Locked = 'locked';
}
