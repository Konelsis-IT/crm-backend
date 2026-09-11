<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HandoffVersionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Superseded => 'warning',
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
            self::Draft => [self::Submitted, self::Superseded],
            self::Submitted => [self::Accepted, self::Rejected, self::Superseded],
            self::Rejected => [self::Superseded],
            self::Accepted => [],
            self::Superseded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
