<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FocusDirection: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Initial = 'initial';
    case Forward = 'forward';
    case Backward = 'backward';

    public function getColor(): string
    {
        return match ($this) {
            self::Initial => 'gray',
            self::Forward => 'success',
            self::Backward => 'warning',
        };
    }
}
