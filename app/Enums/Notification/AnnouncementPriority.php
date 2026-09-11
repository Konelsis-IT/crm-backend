<?php

declare(strict_types=1);

namespace App\Enums\Notification;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AnnouncementPriority: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';

    public function getColor(): string
    {
        return match ($this) {
            self::Normal => 'primary',
            self::Important => 'warning',
            self::Urgent => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Normal => Heroicon::OutlinedMegaphone,
            self::Important => Heroicon::OutlinedExclamationCircle,
            self::Urgent => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
