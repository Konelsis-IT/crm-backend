<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkstreamStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case NotReady = 'not_ready';
    case Ready = 'ready';
    case Active = 'active';
    case Review = 'review';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Waived = 'waived';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::NotReady => 'gray',
            self::Ready => 'info',
            self::Active => 'success',
            self::Review => 'warning',
            self::Completed => 'primary',
            self::Blocked => 'danger',
            self::Waived => 'gray',
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
            self::NotReady => [self::Ready, self::Waived, self::Cancelled],
            self::Ready => [self::Active, self::Waived, self::Cancelled],
            self::Active => [self::Review, self::Blocked, self::Waived, self::Cancelled],
            self::Review => [self::Completed, self::Active, self::Blocked],
            self::Blocked => [self::Active],
            self::Completed => [],
            self::Waived => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
