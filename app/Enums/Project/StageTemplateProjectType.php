<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StageTemplateProjectType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Generic = 'generic';
    case Ges = 'ges';
    case Hes = 'hes';
    case Res = 'res';
    case Bess = 'bess';
    case Ems = 'ems';
    case Enh = 'enh';
    case Mixed = 'mixed';

    public function getColor(): string
    {
        return match ($this) {
            self::Generic => 'gray',
            self::Ges => 'warning',
            self::Hes => 'info',
            self::Res => 'info',
            self::Bess => 'success',
            self::Ems => 'primary',
            self::Enh => 'gray',
            self::Mixed => 'gray',
        };
    }
}
