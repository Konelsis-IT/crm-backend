<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Siparisin durumu ve lojistik asamalari (D-192, B53; kullanici: "Siparisler
 * ilgili teklif onaylandiginda olusan surec ... logistic sureci de olmasi
 * lazim").
 *
 * Taslak (secili tekliften acildi) -> Onay bekliyor (tutar sinirina gore
 * Satin alma muduru / Yonetim kurulu baskani) -> Onaylandi -> Siparis verildi
 * (tedarikciye iletildi) -> Yolda (kargo / nakliye) -> Teslim alindi ->
 * Tamamlandi. Onay gerekmeyen sipariste Taslak'tan dogrudan Siparis verildi'ye
 * gecilir. Iptal her acik asamada mumkundur.
 */
enum PurchaseOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::AwaitingApproval => 'warning',
            self::Approved => 'success',
            self::Ordered => 'info',
            self::InTransit => 'primary',
            self::Delivered => 'success',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::AwaitingApproval => Heroicon::OutlinedShieldExclamation,
            self::Approved => Heroicon::OutlinedShieldCheck,
            self::Ordered => Heroicon::OutlinedShoppingCart,
            self::InTransit => Heroicon::OutlinedTruck,
            self::Delivered => Heroicon::OutlinedArchiveBoxArrowDown,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Lojistik zaman cizelgesindeki asamalar (iptal haric), sirasiyla. */
    public static function stages(): array
    {
        return [self::Draft, self::AwaitingApproval, self::Approved, self::Ordered, self::InTransit, self::Delivered, self::Completed];
    }

    /** Onay saglanmis ya da onay sonrasi asamalar. */
    public function isApprovedOrLater(): bool
    {
        return in_array($this, [self::Approved, self::Ordered, self::InTransit, self::Delivered, self::Completed], true);
    }

    /** Tedarikciye iletilmis, teslimi beklenen asamalar. */
    public function isOnTheWay(): bool
    {
        return in_array($this, [self::Ordered, self::InTransit], true);
    }
}
