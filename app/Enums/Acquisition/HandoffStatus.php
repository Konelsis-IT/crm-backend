<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HandoffStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Preparing = 'preparing';
    case InReview = 'in_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Preparing => 'gray',
            self::InReview => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Izin verilen durum gecisleri (docs/planning/14).
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Preparing => [self::InReview, self::Cancelled],
            self::InReview => [self::Accepted, self::Rejected, self::Cancelled],
            self::Rejected => [self::Preparing],
            self::Accepted => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
