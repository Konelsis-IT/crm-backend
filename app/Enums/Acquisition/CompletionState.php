<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CompletionState: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Complete = 'complete';
    case Waived = 'waived';
    case NotApplicable = 'not_applicable';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Complete => 'success',
            self::Waived => 'warning',
            self::NotApplicable => 'gray',
        };
    }
}
