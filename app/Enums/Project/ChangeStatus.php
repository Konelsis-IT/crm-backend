<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChangeStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Evaluating = 'evaluating';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Implemented = 'implemented';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Evaluating => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Implemented => 'primary',
            self::Cancelled => 'gray',
        };
    }
}
