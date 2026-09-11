<?php

declare(strict_types=1);

namespace App\Enums\WorkRequest;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Talebin muhatabi: bir kisi ya da bir departman (D-84). */
enum RequestTargetKind: string implements HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Personnel = 'personnel';
    case OrgUnit = 'org_unit';

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Personnel => Heroicon::OutlinedUser,
            self::OrgUnit => Heroicon::OutlinedBuildingOffice2,
        };
    }
}
