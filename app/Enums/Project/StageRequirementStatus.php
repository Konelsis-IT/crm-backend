<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StageRequirementStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Waived = 'waived';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Submitted => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Waived => 'warning',
        };
    }
}
