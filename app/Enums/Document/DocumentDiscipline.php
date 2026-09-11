<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentDiscipline: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case General = 'general';
    case Commercial = 'commercial';
    case Engineering = 'engineering';
    case Electrical = 'electrical';
    case Automation = 'automation';
    case Civil = 'civil';
    case Hse = 'hse';
    case Quality = 'quality';
    case Hr = 'hr';
    case Finance = 'finance';
    case Legal = 'legal';
    case SocialMedia = 'social_media';

    public function getColor(): string
    {
        return match ($this) {
            self::General => 'gray',
            self::Commercial => 'info',
            self::Engineering => 'primary',
            self::Electrical => 'warning',
            self::Automation => 'info',
            self::Civil => 'gray',
            self::Hse => 'danger',
            self::Quality => 'success',
            self::Hr => 'primary',
            self::Finance => 'success',
            self::Legal => 'danger',
            self::SocialMedia => 'warning',
        };
    }
}
