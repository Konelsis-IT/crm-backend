<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Kalemli satin alma talebinin durumu (D-192, B53). Kalemlerin durumundan
 * hesaplanir (PurchaseRequestService::syncStatus): hic kalem islenmediyse
 * Acik, en az biri teklif / siparis asamasindaysa Isleniyor, hepsi kapandiysa
 * Tamamlandi; talep eden ya da satin alma iptal edebilir.
 */
enum PurchaseRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Open => Heroicon::OutlinedInboxArrowDown,
            self::InProgress => Heroicon::OutlinedArrowPath,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
