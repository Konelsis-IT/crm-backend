<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestStepStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Waiting = 'waiting';
    case Active = 'active';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Expired = 'expired';

    public function getColor(): string
    {
        return match ($this) {
            self::Waiting => 'gray',
            self::Active => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Skipped => 'gray',
            self::Expired => 'warning',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Skipped, self::Expired], true);
    }
}
