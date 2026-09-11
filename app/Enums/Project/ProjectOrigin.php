<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Projenin nasil dogdugu (D-68): devir kabulu, tekliften donusum veya
 * dogrudan olusturma (gecmis/aktif projelerin sisteme alinmasi).
 */
enum ProjectOrigin: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Handoff = 'handoff';
    case Converted = 'converted';
    case Direct = 'direct';

    public function getColor(): string
    {
        return match ($this) {
            self::Handoff => 'success',
            self::Converted => 'info',
            self::Direct => 'gray',
        };
    }
}
