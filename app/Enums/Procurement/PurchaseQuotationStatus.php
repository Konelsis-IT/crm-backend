<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Satin alma teklifinin (tedarikci teklifi) durumu (D-192, B53).
 *
 * Taslak: Satin alinacaklar'dan secilen kalemlerle istenen, fiyati henuz
 * girilmemis teklif. Alindi: tedarikcinin fiyatlari girildi (elle ya da
 * Konelsis AI belgeden). Secildi: satin alma en uygun teklif olarak secti;
 * siparise donusturulebilir. Reddedildi: ayni kalemler icin baska teklif
 * secildi ya da elle reddedildi. Siparis verildi: tekliften siparis acildi
 * (teklif artik degismez). Iptal.
 */
enum PurchaseQuotationStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Received = 'received';
    case Selected = 'selected';
    case Rejected = 'rejected';
    case Ordered = 'ordered';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Received => 'info',
            self::Selected => 'primary',
            self::Rejected => 'danger',
            self::Ordered => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Received => Heroicon::OutlinedInboxArrowDown,
            self::Selected => Heroicon::OutlinedStar,
            self::Rejected => Heroicon::OutlinedHandThumbDown,
            self::Ordered => Heroicon::OutlinedShoppingCart,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    /** Fiyat, kalem ve belge degisebilir mi? */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Received, self::Selected], true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Ordered, self::Cancelled, self::Rejected], true);
    }
}
