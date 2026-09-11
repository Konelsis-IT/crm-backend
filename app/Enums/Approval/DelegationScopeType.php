<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum DelegationScopeType: string implements HasLabel
{
    use HasTranslatedLabel;

    case All = 'all';
    case OrgUnit = 'org_unit';
    case Project = 'project';
    case FunctionalArea = 'functional_area';
    case ApprovalPolicy = 'approval_policy';
}
