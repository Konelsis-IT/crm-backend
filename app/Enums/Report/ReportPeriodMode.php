<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Raporun donem bicimi (D-86): yok, gun, hafta (Pzt-Paz), ay, serbest aralik.
 * Gun/hafta/ay tek bir tarih secimiyle girilir; servis donemi normalize eder.
 */
enum ReportPeriodMode: string implements HasLabel
{
    use HasTranslatedLabel;

    case None = 'none';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Range = 'range';

    /** Tek tarih seciminden turetilen donemler. */
    public function isCalendarUnit(): bool
    {
        return in_array($this, [self::Day, self::Week, self::Month], true);
    }
}
