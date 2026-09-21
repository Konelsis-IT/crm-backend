<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\MeetingReminderStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Gonderilmis gorusme hatirlatmasi (B34, D-109); tekrar gondermeyi onler. */
#[Table('meeting_plan_reminders')]
#[Fillable(['meeting_plan_id', 'stage', 'due_on', 'recipient_count', 'sent_at'])]
class MeetingPlanReminder extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => MeetingReminderStage::class,
            'due_on' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
