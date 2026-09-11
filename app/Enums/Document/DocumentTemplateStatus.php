<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentTemplateStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Retired = 'retired';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Retired => 'gray',
        };
    }
}
