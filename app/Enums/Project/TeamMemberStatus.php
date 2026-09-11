<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Ekip uyeliginin durumu (11 SS1.12). */
enum TeamMemberStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Ended = 'ended';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Ended => 'gray',
        };
    }
}
