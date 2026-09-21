<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Gorusme plani durumu (B34, D-109). "Geciken" ayri bir durum degildir:
 * tarihi gecmis planli gorusme ekranda geciken olarak gosterilir.
 */
enum MeetingPlanStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Done => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Planned => Heroicon::OutlinedCalendarDays,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    /** Takvim cipi rengi (sosyal medya takviminin palet adlari). */
    public function paletteColor(bool $overdue = false): string
    {
        if ($overdue) {
            return 'rose';
        }

        return match ($this) {
            self::Planned => 'sky',
            self::Done => 'emerald',
            self::Cancelled => 'stone',
        };
    }
}
