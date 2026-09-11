<?php

declare(strict_types=1);

namespace App\Enums\Chat;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum MembershipRole: string implements HasLabel
{
    use HasTranslatedLabel;

    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Observer = 'observer';
}
