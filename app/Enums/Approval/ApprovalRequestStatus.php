<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * SM-APR (14 SS2.38): pending -> in_progress -> approved | rejected;
 * pending/in_progress -> cancelled | expired | invalidated.
 */
enum ApprovalRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Invalidated = 'invalidated';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
            self::Expired => 'warning',
            self::Invalidated => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Cancelled => Heroicon::OutlinedNoSymbol,
            self::Expired => Heroicon::OutlinedExclamationTriangle,
            self::Invalidated => Heroicon::OutlinedArrowPath,
        };
    }

    /** Talep hala acik (karar bekliyor) mu? */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress], true);
    }
}
