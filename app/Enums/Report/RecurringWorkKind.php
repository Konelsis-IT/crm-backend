<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Tekrarli isin turu (D-219): beklenen rapor, kontrol edilecek mail kutusu ya da duzenli is. */
enum RecurringWorkKind: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Report = 'report';
    case Mailbox = 'mailbox';
    case Task = 'task';

    public function getColor(): string
    {
        return match ($this) {
            self::Report => 'info',
            self::Mailbox => 'warning',
            self::Task => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Report => Heroicon::OutlinedDocumentChartBar,
            self::Mailbox => Heroicon::OutlinedInbox,
            self::Task => Heroicon::OutlinedArrowPath,
        };
    }
}
