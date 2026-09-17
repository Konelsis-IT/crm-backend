<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Gonderilen raporu kim inceler (D-86): kimse (gonderim son adim), dogrudan
 * amir (reporting_relationships `line`) ya da yazarin departman yoneticisi.
 */
enum ReportReviewMode: string implements HasLabel
{
    use HasTranslatedLabel;

    case None = 'none';
    case LineManager = 'line_manager';
    case OrgUnitManager = 'org_unit_manager';
}
