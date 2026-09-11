<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkPackageStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case Ready = 'ready';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Ready => 'info',
            self::Active => 'success',
            self::Completed => 'primary',
            self::Cancelled => 'danger',
        };
    }
}
