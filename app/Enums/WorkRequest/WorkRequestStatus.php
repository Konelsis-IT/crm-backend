<?php

declare(strict_types=1);

namespace App\Enums\WorkRequest;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Talep durumu (D-84, D-87): acik -> devam ediyor -> tamamlandi; onaya tabi
 * talepte muhatap tamamlayinca "onay bekliyor" araya girer (B11D): onaylanirsa
 * tamamlanir, reddedilirse devam ediyora doner. Her acik durumdan ret ve iptal
 * mumkun. Tamamlanan/reddedilen/iptal edilen talep degismez.
 */
enum WorkRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case InProgress = 'in_progress';
    case AwaitingApproval = 'awaiting_approval';
    case Done = 'done';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::AwaitingApproval, self::Done, self::Rejected, self::Cancelled],
            self::InProgress => [self::AwaitingApproval, self::Done, self::Rejected, self::Cancelled],
            self::AwaitingApproval => [self::Done, self::InProgress, self::Cancelled],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /** Muhatabin uzerinde is olan durumlar. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }

    /** Henuz kapanmamis durumlar (onay bekleyen dahil). */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::AwaitingApproval], true);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::AwaitingApproval => 'primary',
            self::Done => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Open => Heroicon::OutlinedInbox,
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::AwaitingApproval => Heroicon::OutlinedCheckBadge,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Cancelled => Heroicon::OutlinedNoSymbol,
        };
    }
}
