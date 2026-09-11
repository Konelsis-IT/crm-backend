<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TemplateVersionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Published = 'published';
    case Superseded = 'superseded';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Superseded => 'warning',
        };
    }
}
