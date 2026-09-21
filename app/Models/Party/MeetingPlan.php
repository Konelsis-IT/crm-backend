<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\MeetingChannel;
use App\Enums\Party\MeetingPlanSource;
use App\Enums\Party\MeetingPlanStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\MeetingPlanPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Gorusme plani (B34, D-109): kim, ne zaman, hangi tarafla gorusecek /
 * gorustu. Gorusme notlari buraya yansir (meeting_note_id); notun tarihli
 * sonraki adimi planli satirdir (follow_up_note_id).
 */
#[Table('meeting_plans')]
#[Fillable([
    'party_id', 'contact_relationship_id', 'personnel_id', 'planned_on', 'channel', 'subject', 'note',
    'status', 'source', 'meeting_note_id', 'follow_up_note_id', 'completed_at',
])]
#[UsePolicy(MeetingPlanPolicy::class)]
class MeetingPlan extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_on' => 'date',
            'channel' => MeetingChannel::class,
            'status' => MeetingPlanStatus::class,
            'source' => MeetingPlanSource::class,
            'completed_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactRelationship::class, 'contact_relationship_id');
    }

    /** Sorumlu personel (gorusmeyi yapacak / yapan). */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function participantRows(): HasMany
    {
        return $this->hasMany(MeetingPlanParticipant::class, 'meeting_plan_id');
    }

    /** Gorusmeye katilacak diger personel. */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Personnel::class, 'meeting_plan_participants', 'meeting_plan_id', 'personnel_id');
    }

    /** Gorusmenin sonuc notu (Taraf > Gorusme notlari). */
    public function meetingNote(): BelongsTo
    {
        return $this->belongsTo(PartyMeetingNote::class, 'meeting_note_id');
    }

    /** Bu plan hangi notun sonraki adimi. */
    public function followUpNote(): BelongsTo
    {
        return $this->belongsTo(PartyMeetingNote::class, 'follow_up_note_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(MeetingPlanReminder::class, 'meeting_plan_id');
    }

    public function isPlanned(): bool
    {
        return $this->status === MeetingPlanStatus::Planned;
    }

    /** Tarihi gecmis ama sonucu girilmemis planli gorusme. */
    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->isPlanned()
            && $this->planned_on !== null
            && $this->planned_on->lt(($today ?? Carbon::today())->startOfDay());
    }

    public function isFollowUp(): bool
    {
        return $this->source === MeetingPlanSource::FollowUp;
    }
}
