<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use App\Services\Platform\SchemaReadiness;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Pano is kaleminin durumu (D-86): planlandi, devam ediyor, bekleniyor
 * (B36, D-115: is panosundan dondurulan kart), tamamlandi, engellendi.
 */
enum ReportItemStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Done = 'done';
    case Blocked = 'blocked';

    /** Bir sonraki donemin panosuna tasinir mi? */
    public function carriesOver(): bool
    {
        return $this !== self::Done;
    }

    /**
     * Bu ortamda kullanilabilen durumlar: "Bekleniyor" B36 uygulanmadan
     * veritabanina yazilamaz (report_items durum CHECK'i).
     *
     * @return list<self>
     */
    public static function available(): array
    {
        if (SchemaReadiness::hasBatch('B36')) {
            return self::cases();
        }

        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status !== self::Waiting));
    }

    /**
     * @return array<string, string>
     */
    public static function availableOptions(): array
    {
        $options = [];

        foreach (self::available() as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'info',
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
