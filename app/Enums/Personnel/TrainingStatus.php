<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TrainingStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }
}
