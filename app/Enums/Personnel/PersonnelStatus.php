<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PersonnelStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Invited = 'invited';
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Separated = 'separated';

    public function getColor(): string
    {
        return match ($this) {
            self::Invited => 'info',
            self::Active => 'success',
            self::OnLeave => 'warning',
            self::Suspended => 'danger',
            self::Separated => 'gray',
        };
    }

    /**
     * Izin verilen durum gecisleri.
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Invited => [self::Active, self::Separated],
            self::Active => [self::OnLeave, self::Suspended, self::Separated],
            self::OnLeave => [self::Active, self::Suspended, self::Separated],
            self::Suspended => [self::Active, self::Separated],
            self::Separated => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
