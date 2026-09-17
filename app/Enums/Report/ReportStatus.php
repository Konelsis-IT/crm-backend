<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Rapor durumu (D-86): taslak -> gonderildi -> onaylandi | revizyon istendi |
 * reddedildi. Revizyon istenen rapor yazarinca duzenlenip yeniden gonderilir;
 * gonderilmis rapor inceleme baslamadan geri cekilebilir (-> taslak).
 * Inceleme gerektirmeyen taslaklarda "gonderildi" son durumdur.
 */
enum ReportStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case RevisionRequired = 'revision_required';
    case Rejected = 'rejected';

    /**
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Draft, self::Approved, self::RevisionRequired, self::Rejected],
            self::RevisionRequired => [self::Submitted],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /** Yazar icerigi degistirebilir mi? */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::RevisionRequired], true);
    }

    /** Inceleyenin karar verebilecegi durum. */
    public function isReviewable(): bool
    {
        return $this === self::Submitted;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::RevisionRequired => 'info',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Submitted => Heroicon::OutlinedPaperAirplane,
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::RevisionRequired => Heroicon::OutlinedArrowUturnLeft,
            self::Rejected => Heroicon::OutlinedXCircle,
        };
    }
}
