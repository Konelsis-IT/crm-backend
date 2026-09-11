<?php

declare(strict_types=1);

namespace App\Enums\Notification;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Son tarih uyarisi seviyesi (07 SS5.4 `severity`): kalan gune gore
 * warning (>3 gun) -> high (1-3 gun) -> critical (bugun/gecmis).
 */
enum AlertSeverity: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Warning = 'warning';
    case High = 'high';
    case Critical = 'critical';

    public static function forDaysLeft(int $days): self
    {
        return match (true) {
            $days > 3 => self::Warning,
            $days >= 1 => self::High,
            default => self::Critical,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Warning => 1,
            self::High => 2,
            self::Critical => 3,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Warning => 'warning',
            self::High => 'danger',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Warning => Heroicon::OutlinedClock,
            self::High => Heroicon::OutlinedExclamationCircle,
            self::Critical => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
