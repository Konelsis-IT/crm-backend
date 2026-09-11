<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BaselineSource: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Manual = 'manual';
    case MsProjectImport = 'ms_project_import';

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::MsProjectImport => 'info',
        };
    }
}
