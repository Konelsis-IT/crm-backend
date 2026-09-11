<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportingScopeType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case All = 'all';
    case OrgUnit = 'org_unit';
    case Project = 'project';
    case FunctionalArea = 'functional_area';

    public function getColor(): string
    {
        return match ($this) {
            self::All => 'gray',
            self::OrgUnit => 'primary',
            self::Project => 'info',
            self::FunctionalArea => 'warning',
        };
    }
}
