<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Siparis kaleminin durumu (D-189). Teklif maliyetinden gelen kalem proje
 * onayina kadar AwaitingProject'tir ("proje onay vermeden satin alima
 * dusmeyecek"); sonra Open -> Quoting (ilk tedarikci teklifi) -> Recommended
 * (satin alma en uygun teklifi secti) -> AwaitingApproval / Approved (tutar
 * sinirina gore onay motoru) -> Ordered -> Completed. Onay gereken kalem
 * onaysiz Ordered / Completed olamaz (servis kurali, ekranda uyari penceresi).
 */
enum PurchaseNeedStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case AwaitingProject = 'awaiting_project';
    case Open = 'open';
    case Quoting = 'quoting';
    case Recommended = 'recommended';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::AwaitingProject => 'gray',
            self::Open => 'warning',
            self::Quoting => 'info',
            self::Recommended => 'primary',
            self::AwaitingApproval => 'warning',
            self::Approved => 'success',
            self::Ordered => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::AwaitingProject => Heroicon::OutlinedClock,
            self::Open => Heroicon::OutlinedInboxArrowDown,
            self::Quoting => Heroicon::OutlinedScale,
            self::Recommended => Heroicon::OutlinedStar,
            self::AwaitingApproval => Heroicon::OutlinedShieldExclamation,
            self::Approved => Heroicon::OutlinedShieldCheck,
            self::Ordered => Heroicon::OutlinedTruck,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    /** Henuz siparisi verilmemis, satin almanin is listesinde duran durumlar. */
    public static function pendingOrder(): array
    {
        return [self::Open, self::Quoting, self::Recommended, self::AwaitingApproval, self::Approved];
    }

    /** Kapanmis durumlar (termin uyarisi ve acik sayimlar disinda). */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
