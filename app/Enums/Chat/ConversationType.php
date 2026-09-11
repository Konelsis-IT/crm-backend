<?php

declare(strict_types=1);

namespace App\Enums\Chat;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** 08 SS2.1 `conversation_type`; bu dilimde `direct` ve `group` kullanilir. */
enum ConversationType: string implements HasLabel
{
    use HasTranslatedLabel;

    case Company = 'company';
    case FunctionalArea = 'functional_area';
    case Department = 'department';
    case Project = 'project';
    case Group = 'group';
    case Direct = 'direct';
}
