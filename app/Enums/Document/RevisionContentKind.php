<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Revizyonun icerigi nereden geliyor: yuklenen dosya mi, sistemde yazilan
 * belge mi (D-75).
 */
enum RevisionContentKind: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Upload = 'upload';
    case Authored = 'authored';

    public function getColor(): string
    {
        return match ($this) {
            self::Upload => 'info',
            self::Authored => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Upload => Heroicon::OutlinedArrowUpTray,
            self::Authored => Heroicon::OutlinedPencilSquare,
        };
    }
}
