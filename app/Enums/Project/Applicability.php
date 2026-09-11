<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Applicability: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Applicable = 'applicable';
    case NotApplicable = 'not_applicable';

    public function getColor(): string
    {
        return match ($this) {
            self::Applicable => 'success',
            self::NotApplicable => 'gray',
        };
    }
}
