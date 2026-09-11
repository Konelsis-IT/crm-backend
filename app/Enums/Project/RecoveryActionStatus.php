<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RecoveryActionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Cancelled => 'danger',
        };
    }
}
