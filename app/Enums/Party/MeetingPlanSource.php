<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Gorusme plani satirinin kaynagi (B34, D-109): elle planlandi, gorusme
 * notundan yansidi, notun sonraki adimi ya da haftalik ziyaret plani aktarimi.
 */
enum MeetingPlanSource: string implements HasLabel
{
    use HasTranslatedLabel;

    case Manual = 'manual';
    case MeetingNote = 'meeting_note';
    case FollowUp = 'follow_up';
    case Import = 'import';
}
