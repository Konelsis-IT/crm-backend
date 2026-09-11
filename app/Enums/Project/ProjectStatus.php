<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Opening = 'opening';
    case Active = 'active';
    case Acceptance = 'acceptance';
    case Warranty = 'warranty';
    case Closed = 'closed';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Opening => 'gray',
            self::Active => 'success',
            self::Acceptance => 'info',
            self::Warranty => 'primary',
            self::Closed => 'gray',
            self::Suspended => 'warning',
            self::Cancelled => 'danger',
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
            self::Opening => [self::Active, self::Cancelled],
            self::Active => [self::Acceptance, self::Suspended, self::Cancelled],
            self::Acceptance => [self::Warranty, self::Suspended, self::Cancelled],
            self::Warranty => [self::Closed],
            self::Suspended => [self::Active, self::Cancelled],
            self::Closed => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
