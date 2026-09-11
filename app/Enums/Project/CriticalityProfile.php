<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CriticalityProfile: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Standard = 'standard';
    case Critical = 'critical';
    case Strategic = 'strategic';

    public function getColor(): string
    {
        return match ($this) {
            self::Standard => 'gray',
            self::Critical => 'warning',
            self::Strategic => 'danger',
        };
    }
}
