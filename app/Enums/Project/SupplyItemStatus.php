<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tedarik kaleminin satin alma -> lojistik -> saha yolculugu (11 SS1.11).
 * Gecisler serbesttir (geri alma dahil); tarih damgalari servis katmaninda.
 */
enum SupplyItemStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case Requested = 'requested';
    case Ordered = 'ordered';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Installed = 'installed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Requested => 'warning',
            self::Ordered => 'info',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Installed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Siparisi verilmis (satin alma adimi tamam) sayilan durumlar. */
    public static function orderedStates(): array
    {
        return [self::Ordered, self::Shipped, self::Delivered, self::Installed];
    }

    /** Sahaya ulasmis (lojistik adimi tamam) sayilan durumlar. */
    public static function deliveredStates(): array
    {
        return [self::Delivered, self::Installed];
    }
}
