<?php

declare(strict_types=1);

namespace App\Enums\Party;

/** Gorusme hatirlatmasi asamasi (B34, D-109): 1 gun once ve gunun sabahi. */
enum MeetingReminderStage: string
{
    case DayBefore = 'day_before';
    case SameDay = 'same_day';
}
