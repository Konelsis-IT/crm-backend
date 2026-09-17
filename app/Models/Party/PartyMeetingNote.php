<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\MeetingChannel;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Policies\PartyMeetingNotePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarafla yapilan gorusme notu (B28, D-98): tarih, kanal, gorusen Konelsis
 * personeli, karsidaki kisi, not ve bir sonraki adim.
 */
#[Table('party_meeting_notes')]
#[Fillable([
    'party_id', 'contact_relationship_id', 'personnel_id', 'noted_on', 'channel', 'subject', 'note',
    'next_action_on', 'next_action',
])]
#[UsePolicy(PartyMeetingNotePolicy::class)]
class PartyMeetingNote extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noted_on' => 'date',
            'channel' => MeetingChannel::class,
            'next_action_on' => 'date',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /** Gorusulen kisi; bos ise gorusme kurumun kendisiyle yapilmistir. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactRelationship::class, 'contact_relationship_id');
    }

    /** Gorusmeyi yapan Konelsis personeli. */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }
}
