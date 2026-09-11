<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ComponentScopeState: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case InScope = 'in_scope';
    case OutOfScope = 'out_of_scope';
    case Completed = 'completed';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InScope => 'success',
            self::OutOfScope => 'danger',
            self::Completed => 'primary',
        };
    }
}
