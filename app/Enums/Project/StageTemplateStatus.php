<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StageTemplateStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Retired => 'gray',
        };
    }
}
