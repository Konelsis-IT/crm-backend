<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum InvalidationReason: string implements HasLabel
{
    use HasTranslatedLabel;

    case HashMismatch = 'hash_mismatch';
    case SubjectWithdrawn = 'subject_withdrawn';
    case PolicySuperseded = 'policy_superseded';
}
