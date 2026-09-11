<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum RetentionTriggerKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case CreatedAt = 'created_at';
    case ClosedAt = 'closed_at';
    case ArchivedAt = 'archived_at';
    case Separation = 'separation';
    case ProjectClose = 'project_close';
    case Manual = 'manual';
}
