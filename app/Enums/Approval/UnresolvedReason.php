<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum UnresolvedReason: string implements HasLabel
{
    use HasTranslatedLabel;

    case VacantPosition = 'vacant_position';
    case InactiveUser = 'inactive_user';
    case NoManager = 'no_manager';
    case NoRoleHolder = 'no_role_holder';
    case SodConflict = 'sod_conflict';
}
