<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Isin onceligi (D-219, 16 Ekim 2026 kullanici: "Evet kritikliği 4 seviyeye
 * çıkar. Bunu da yine dashboard ve iş panosunda kritikliği görelim").
 * Eski kritik bayragi (`is_critical`) Kritik ile esit tutulur; uyarilar ve
 * acil seridi onu okumaya devam eder.
 */
enum WorkItemPriority: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    /** Siralama: kritik once. */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::High => 1,
            self::Normal => 2,
            self::Low => 3,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Low => Heroicon::OutlinedArrowDown,
            self::Normal => Heroicon::OutlinedMinus,
            self::High => Heroicon::OutlinedArrowUp,
            self::Critical => Heroicon::OutlinedFire,
        };
    }

    /** Eski kritik bayragindan. */
    public static function fromCritical(bool $critical): self
    {
        return $critical ? self::Critical : self::Normal;
    }
}
