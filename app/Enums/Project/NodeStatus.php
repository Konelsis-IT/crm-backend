<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NodeStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Closed = 'closed';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Closed => 'gray',
        };
    }
}
