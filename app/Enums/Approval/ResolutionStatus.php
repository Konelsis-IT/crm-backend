<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ResolutionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Resolved = 'resolved';
    case Unresolved = 'unresolved';

    public function getColor(): string
    {
        return match ($this) {
            self::Resolved => 'success',
            self::Unresolved => 'danger',
        };
    }
}
