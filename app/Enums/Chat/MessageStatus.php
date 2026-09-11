<?php

declare(strict_types=1);

namespace App\Enums\Chat;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum MessageStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Sent = 'sent';
    case Edited = 'edited';
    case Redacted = 'redacted';
    case RemovedByPolicy = 'removed_by_policy';
}
