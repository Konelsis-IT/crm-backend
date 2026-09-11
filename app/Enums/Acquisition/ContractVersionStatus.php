<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractVersionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Executed = 'executed';
    case Superseded = 'superseded';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Review => 'info',
            self::Approved => 'success',
            self::Executed => 'primary',
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
            self::Draft => [self::Review],
            self::Review => [self::Approved, self::Draft],
            self::Approved => [self::Executed, self::Superseded],
            self::Executed => [self::Superseded],
            self::Superseded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
