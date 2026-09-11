<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AcknowledgementKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Read = 'read';
    case Accepted = 'accepted';
    case Trained = 'trained';

    public function getColor(): string
    {
        return match ($this) {
            self::Read => 'gray',
            self::Accepted => 'success',
            self::Trained => 'primary',
        };
    }
}
