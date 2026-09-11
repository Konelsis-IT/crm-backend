<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CompetencyLevel: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Expert = 'expert';

    public function getColor(): string
    {
        return match ($this) {
            self::Beginner => 'gray',
            self::Intermediate => 'info',
            self::Advanced => 'warning',
            self::Expert => 'success',
        };
    }
}
