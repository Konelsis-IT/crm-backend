<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Pano is kaleminin durumu (D-86): planlandi, devam ediyor, tamamlandi, engellendi. */
enum ReportItemStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Blocked = 'blocked';

    /** Bir sonraki donemin panosuna tasinir mi? */
    public function carriesOver(): bool
    {
        return $this !== self::Done;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Blocked => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Planned => Heroicon::OutlinedClipboardDocumentList,
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Blocked => Heroicon::OutlinedHandRaised,
        };
    }
}
