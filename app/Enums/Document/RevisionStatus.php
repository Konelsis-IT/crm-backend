<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RevisionStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Issued = 'issued';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'info',
            self::Approved => 'success',
            self::Issued => 'success',
            self::Superseded => 'warning',
            self::Withdrawn => 'danger',
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
            self::Draft => [self::InReview, self::Withdrawn],
            self::InReview => [self::Approved, self::Draft, self::Withdrawn],
            self::Approved => [self::Issued, self::Draft],
            self::Issued => [self::Superseded],
            self::Superseded => [],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
