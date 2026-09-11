<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GeneratedOutputStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Queued = 'queued';
    case Rendering = 'rendering';
    case Completed = 'completed';
    case Failed = 'failed';
    case BlockedMissingFields = 'blocked_missing_fields';
    case Superseded = 'superseded';

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Rendering => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::BlockedMissingFields => 'warning',
            self::Superseded => 'gray',
        };
    }
}
