<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProposalVersionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Submitted = 'submitted';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Review => 'info',
            self::Approved => 'success',
            self::Submitted => 'primary',
            self::Superseded => 'warning',
            self::Withdrawn => 'danger',
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
            self::Draft => [self::Review, self::Withdrawn],
            self::Review => [self::Approved, self::Draft, self::Withdrawn],
            self::Approved => [self::Submitted, self::Superseded, self::Withdrawn],
            self::Submitted => [self::Superseded],
            self::Superseded => [],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
