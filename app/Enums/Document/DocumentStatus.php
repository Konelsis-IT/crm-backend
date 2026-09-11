<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
    case Obsolete = 'obsolete';
    case OnHold = 'on_hold';
    case Archived = 'archived';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Superseded => 'warning',
            self::Obsolete => 'danger',
            self::OnHold => 'warning',
            self::Archived => 'gray',
        };
    }
}
