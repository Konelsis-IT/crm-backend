<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Plan tarihine gore asama (B31, D-106): yaklasiyor, yarin, bugun, gecikti.
 * Gun farki yalniz SocialClock uzerinden hesaplanir.
 */
enum SocialReminderStage: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Approaching = 'approaching';
    case Tomorrow = 'tomorrow';
    case Today = 'today';
    case Missed = 'missed';

    /**
     * Gun farkindan asama: <0 gecikti, 0 bugun, 1 yarin, 2..N yaklasiyor,
     * daha uzak tarih icin null.
     */
    public static function forDays(int $days, int $approachingDays): ?self
    {
        return match (true) {
            $days < 0 => self::Missed,
            $days === 0 => self::Today,
            $days === 1 => self::Tomorrow,
            $days <= $approachingDays => self::Approaching,
            default => null,
        };
    }

    /** Filament rengi (pano widget'i). React renkleri istemcide sabittir. */
    public function getColor(): string
    {
        return match ($this) {
            self::Missed => 'danger',
            self::Today => 'warning',
            self::Tomorrow => 'info',
            self::Approaching => 'gray',
        };
    }
}
