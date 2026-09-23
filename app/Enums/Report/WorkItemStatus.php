<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Is panosu kartinin durumu (B36, D-115): panonun bes sutunu. "Bekleniyor"
 * dis tarafa bagli beklemedir (kimden beklendigi zorunlu); "Engellendi" isin
 * ilerleyemedigi durumdur. Tamamlanmayan kartlar ertesi gunun panosuna devreder.
 */
enum WorkItemStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Done = 'done';
    case Blocked = 'blocked';

    /** Acik kart: tamamlanmamis; sonraki gune devreder. */
    public function isOpen(): bool
    {
        return $this !== self::Done;
    }

    /** Surenin sayildigi kova (sure raporu): calisma, bekleme, engel. */
    public function durationBucket(): ?string
    {
        return match ($this) {
            self::InProgress => 'progress',
            self::Waiting => 'waiting',
            self::Blocked => 'blocked',
            default => null,
        };
    }

    /** Donmus rapor kalemi durumu. */
    public function reportItemStatus(): ReportItemStatus
    {
        return match ($this) {
            self::Planned => ReportItemStatus::Planned,
            self::InProgress => ReportItemStatus::InProgress,
            self::Waiting => ReportItemStatus::Waiting,
            self::Done => ReportItemStatus::Done,
            self::Blocked => ReportItemStatus::Blocked,
        };
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isOpen()),
        ));
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'warning',
            self::Waiting => 'violet',
            self::Done => 'success',
            self::Blocked => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Planned => Heroicon::OutlinedClipboardDocumentList,
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::Waiting => Heroicon::OutlinedClock,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Blocked => Heroicon::OutlinedHandRaised,
        };
    }
}
