<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Rapor turu (D-86): kullanicinin saydigi rapor biçimleri. Taslak (sablon)
 * turu belirler; tur listeleme ve filtreleme icin rapor satirina kopyalanir.
 */
enum ReportKind: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Project = 'project';
    case Product = 'product';
    case Proposal = 'proposal';
    case BusinessCase = 'business_case';
    case Personnel = 'personnel';
    case System = 'system';

    public function getColor(): string
    {
        return match ($this) {
            self::Daily, self::Weekly, self::Monthly => 'info',
            self::Project => 'primary',
            self::Product => 'warning',
            self::Proposal, self::BusinessCase => 'success',
            self::Personnel => 'danger',
            self::System => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Daily => Heroicon::OutlinedSun,
            self::Weekly => Heroicon::OutlinedCalendarDays,
            self::Monthly => Heroicon::OutlinedCalendar,
            self::Project => Heroicon::OutlinedBriefcase,
            self::Product => Heroicon::OutlinedCube,
            self::Proposal => Heroicon::OutlinedDocumentCurrencyDollar,
            self::BusinessCase => Heroicon::OutlinedFolderOpen,
            self::Personnel => Heroicon::OutlinedUser,
            self::System => Heroicon::OutlinedCpuChip,
        };
    }
}
