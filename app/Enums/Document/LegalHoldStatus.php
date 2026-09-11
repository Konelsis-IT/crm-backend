<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LegalHoldStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Released = 'released';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'danger',
            self::Released => 'success',
        };
    }
}
