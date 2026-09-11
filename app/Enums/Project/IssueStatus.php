<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IssueStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Closed => 'gray',
            self::Cancelled => 'gray',
        };
    }
}
