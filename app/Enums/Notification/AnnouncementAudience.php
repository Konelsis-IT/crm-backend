<?php

declare(strict_types=1);

namespace App\Enums\Notification;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Bildirim/duyuru hedef kitlesi (D-82). Her kitle icin ayri Shield izni
 * vardir (config/filament-shield.php `custom_permissions`: notify:<kitle>).
 */
enum AnnouncementAudience: string implements HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Team = 'team';
    case Department = 'department';
    case Role = 'role';
    case Personnel = 'personnel';
    case All = 'all';

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Team => Heroicon::OutlinedUserGroup,
            self::Department => Heroicon::OutlinedBuildingOffice2,
            self::Role => Heroicon::OutlinedKey,
            self::Personnel => Heroicon::OutlinedUser,
            self::All => Heroicon::OutlinedMegaphone,
        };
    }

    /** Bu kitleye gonderme izninin ozel izin anahtari (notify:team -> Notify:Team). */
    public function permission(): string
    {
        return 'notify:'.$this->value;
    }
}
