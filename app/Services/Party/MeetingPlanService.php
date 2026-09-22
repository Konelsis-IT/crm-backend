<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\MeetingChannel;
use App\Enums\Party\MeetingPlanSource;
use App\Enums\Party\MeetingPlanStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Party\MeetingPlan;
use App\Models\Party\MeetingPlanParticipant;
use App\Models\Party\PartyMeetingNote;
use App\Services\AbstractService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Gorusme plani servisi (B34, D-109).
 *
 * Plan satirlari uc yoldan dogar:
 *  - Elle: Gorusme plani ekranindan (manual) ya da haftalik ziyaret plani
 *    aktarimindan (import).
 *  - Gorusme notu: Taraf > Gorusme notlarina yazilan her not (yeni ya da
 *    gecmis) "gerceklesti" satiri olarak yansir (syncFromNote).
 *  - Sonraki adim: notun tarihli sonraki adimi planli satirdir; hatirlatma
 *    bu satir uzerinden gider (MeetingReminderScanner).
 *
 * Planli gorusmenin sonucu girilince (complete) Gorusme notlarina not
 * yazilir ve plan o nota baglanir; ayni not ikinci bir satir uretmez.
 * Gerceklesmis satir bu servisle duzenlenmez (not uzerinden guncellenir).
 */
final class MeetingPlanService extends AbstractService
{
    protected string $model = MeetingPlan::class;

    protected string $orderBy = 'planned_on';

    /** @var list<string> */
    protected array $with = ['party', 'contact', 'personnel', 'participants'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $participants = $this->pullParticipants($data);

        return $this->transactions->run(function () use ($data, $participants): Model {
            /** @var MeetingPlan $plan */
            $plan = parent::create([
                'status' => MeetingPlanStatus::Planned->value,
                'source' => MeetingPlanSource::Manual->value,
                'personnel_id' => app(ActorContext::class)->personnelId(),
                ...$data,
            ]);

            if ($participants !== null) {
                $this->syncParticipants($plan, $participants);
            }

            return $plan;
        });
    }

    /**
     * Yalniz planli gorusme duzenlenir; durum, kaynak ve not baglari bu yoldan degismez.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $participants = $this->pullParticipants($data);
        unset($data['status'], $data['source'], $data['meeting_note_id'], $data['follow_up_note_id'], $data['completed_at']);

        return $this->transactions->run(function () use ($record, $data, $participants): Model {
            /** @var MeetingPlan $plan */
            $plan = $this->lockForUpdate($record);

            if (! $plan->isPlanned()) {
                throw InvalidTransitionException::make();
            }

            /** @var MeetingPlan $plan */
            $plan = parent::update($plan, $data);

            if ($participants !== null) {
                $this->syncParticipants($plan, $participants);
            }

            return $plan;
        });
    }

    /**
     * Gorusme gerceklesti: Gorusme notlarina not yazilir, plan o nota baglanir.
     * Sonraki adim verilirse notun sonraki adimi olarak yeni bir planli satir dogar.
     */
    public function complete(Model|int|string $record, string $note, ?string $notedOn = null, ?string $nextActionOn = null, ?string $nextAction = null): MeetingPlan
    {
        return $this->transactions->run(function () use ($record, $note, $notedOn, $nextActionOn, $nextAction): MeetingPlan {
            /** @var MeetingPlan $plan */
            $plan = $this->lockForUpdate($record);

            if (! $plan->isPlanned()) {
                throw InvalidTransitionException::make();
            }

            app(PartyMeetingNoteService::class)->create([
                'party_id' => $plan->party_id,
                'contact_relationship_id' => $plan->contact_relationship_id,
                'personnel_id' => $plan->personnel_id ?? app(ActorContext::class)->personnelId(),
                // Gorusme tarihi: verilmediyse plan tarihi (bugun ya da gecmisse), degilse bugun.
                'noted_on' => filled($notedOn) ? $notedOn : Carbon::today(DisplayTime::zone())->min($plan->planned_on ?? Carbon::today(DisplayTime::zone()))->toDateString(),
                'channel' => $plan->channel?->value ?? MeetingChannel::Visit->value,
                'subject' => $plan->subject,
                'note' => trim($note),
                'next_action_on' => filled($nextActionOn) ? $nextActionOn : null,
                'next_action' => filled($nextAction) ? trim((string) $nextAction) : null,
                PartyMeetingNoteService::PLAN_CONTEXT => (int) $plan->getKey(),
            ]);

            return $plan->refresh();
        });
    }

    /** Gorusme gerceklesmedi: plan iptal edilir, neden plan notuna eklenir. */
    public function cancel(Model|int|string $record, ?string $reason = null): MeetingPlan
    {
        return $this->transactions->run(function () use ($record, $reason): MeetingPlan {
            /** @var MeetingPlan $plan */
            $plan = $this->lockForUpdate($record);

            if (! $plan->isPlanned()) {
                throw InvalidTransitionException::make();
            }

            $reason = trim((string) $reason);
            $plan->fill([
                'status' => MeetingPlanStatus::Cancelled->value,
                'note' => $reason === '' ? $plan->note : trim(($plan->note ? $plan->note."\n" : '').$reason),
            ]);

            $changes = $this->saveWithoutVersion($plan);
            $this->recordActivity($plan, 'cancelled', array_filter(['durum' => $changes['status'] ?? null, 'gerekce' => $reason ?: null]));

            return $plan;
        });
    }

    /**
     * Aktarim duzeltmesi: yanlis firmaya baglanmis, nota bagli olmayan satiri
     * dogru firmaya tasir (PartySplitSeeder). Durum, tarih ve katilimcilar
     * degismez; nota bagli satirlar notla birlikte tasinir (syncFromNote).
     */
    public function moveToParty(Model|int|string $record, int $partyId, ?int $contactId): MeetingPlan
    {
        return $this->transactions->run(function () use ($record, $partyId, $contactId): MeetingPlan {
            /** @var MeetingPlan $plan */
            $plan = $this->lockForUpdate($record);

            $plan->fill(['party_id' => $partyId, 'contact_relationship_id' => $contactId]);
            $changes = $this->saveWithoutVersion($plan);

            if ($changes !== []) {
                $this->recordActivity($plan, 'updated', $changes);
            }

            return $plan;
        });
    }

    /** Planli gorusmenin tarihini degistirir (yeni tarih icin hatirlatma yeniden gider). */
    public function reschedule(Model|int|string $record, string $plannedOn): MeetingPlan
    {
        return $this->transactions->run(function () use ($record, $plannedOn): MeetingPlan {
            /** @var MeetingPlan $plan */
            $plan = $this->lockForUpdate($record);

            if (! $plan->isPlanned()) {
                throw InvalidTransitionException::make();
            }

            $plan->fill(['planned_on' => $plannedOn]);
            $changes = $this->saveWithoutVersion($plan);

            if ($changes !== []) {
                $this->recordActivity($plan, 'rescheduled', $changes);
            }

            return $plan;
        });
    }

    /** Katilimci ve hatirlatma satirlari da silinir (gerceklesmemis elle / aktarim satiri). */
    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var MeetingPlan $plan */
            $plan = $this->lockForUpdate($record);

            $plan->participantRows()->delete();
            $plan->reminders()->delete();

            return parent::delete($plan);
        });
    }

    /**
     * Gorusme notunu plana yansitir: not "gerceklesti" satiridir; notun tarihli
     * sonraki adimi planli satirdir. $completedPlanId: not bir planin sonucu
     * olarak yazildiysa o plan nota baglanir (yeni satir acilmaz).
     */
    public function syncFromNote(PartyMeetingNote $note, ?int $completedPlanId = null): void
    {
        if (! SchemaReadiness::hasBatch('B34')) {
            return;
        }

        $this->transactions->run(function () use ($note, $completedPlanId): void {
            $this->syncResultRow($note, $completedPlanId);
            $this->syncFollowUpRow($note);
        });
    }

    /**
     * Not silinmeden once: nottan dogan satir silinir; not bir planin sonucuysa
     * plan yeniden planliya doner. Sonraki adim satiri gerceklesmediyse silinir,
     * gerceklestiyse yalniz bag kopar.
     */
    public function detachNote(PartyMeetingNote $note): void
    {
        if (! SchemaReadiness::hasBatch('B34')) {
            return;
        }

        $this->transactions->run(function () use ($note): void {
            $result = MeetingPlan::query()->where('meeting_note_id', $note->getKey())->first();

            if ($result instanceof MeetingPlan) {
                if ($result->source === MeetingPlanSource::MeetingNote) {
                    $this->delete($result);
                } else {
                    $this->reopen($result);
                }
            }

            $follow = MeetingPlan::query()->where('follow_up_note_id', $note->getKey())->first();

            if ($follow instanceof MeetingPlan) {
                if ($follow->status === MeetingPlanStatus::Done) {
                    $follow->fill(['follow_up_note_id' => null]);
                    $this->saveWithoutVersion($follow);
                } else {
                    $this->delete($follow);
                }
            }
        });
    }

    /**
     * Eksik katilimcilari ekler (aktarim); var olanlara dokunmaz.
     *
     * @param  list<int>  $personnelIds
     */
    public function ensureParticipants(MeetingPlan $plan, array $personnelIds): void
    {
        $existing = $plan->participantRows()->pluck('personnel_id')->map(fn ($id): int => (int) $id)->all();

        foreach (array_unique(array_map('intval', $personnelIds)) as $id) {
            if ($id > 0 && $id !== (int) $plan->personnel_id && ! in_array($id, $existing, true)) {
                MeetingPlanParticipant::query()->create(['meeting_plan_id' => $plan->getKey(), 'personnel_id' => $id]);
            }
        }
    }

    private function syncResultRow(PartyMeetingNote $note, ?int $completedPlanId): void
    {
        $plan = MeetingPlan::query()->where('meeting_note_id', $note->getKey())->first();

        if (! $plan instanceof MeetingPlan && $completedPlanId !== null) {
            $plan = MeetingPlan::query()->find($completedPlanId);
        }

        $values = [
            'party_id' => $note->party_id,
            'contact_relationship_id' => $note->contact_relationship_id,
            'personnel_id' => $note->personnel_id ?? ($plan?->personnel_id),
            'planned_on' => $note->noted_on?->toDateString(),
            'channel' => $this->enumValue($note->channel) ?? MeetingChannel::Other->value,
            'status' => MeetingPlanStatus::Done->value,
            'meeting_note_id' => $note->getKey(),
        ];

        if (! $plan instanceof MeetingPlan) {
            parent::create([
                ...$values,
                'subject' => $this->subjectFor($note),
                'source' => MeetingPlanSource::MeetingNote->value,
                'completed_at' => Carbon::now('UTC'),
            ]);

            return;
        }

        // Elle planlanan gorusmenin konusu korunur; nottan dogan satirin konusu notu izler.
        if ($plan->source === MeetingPlanSource::MeetingNote) {
            $values['subject'] = $this->subjectFor($note);
        }

        if ($plan->completed_at === null) {
            $values['completed_at'] = Carbon::now('UTC');
        }

        $plan->fill($values);
        $changes = $this->saveWithoutVersion($plan);

        if ($changes !== []) {
            $this->recordActivity($plan, $completedPlanId !== null ? 'completed' : 'updated', $changes);
        }
    }

    private function syncFollowUpRow(PartyMeetingNote $note): void
    {
        $follow = MeetingPlan::query()->where('follow_up_note_id', $note->getKey())->first();
        $date = $note->next_action_on?->toDateString();

        if ($date === null) {
            if ($follow instanceof MeetingPlan && $follow->status !== MeetingPlanStatus::Done) {
                $this->delete($follow);
            }

            return;
        }

        $values = [
            'party_id' => $note->party_id,
            'contact_relationship_id' => $note->contact_relationship_id,
            'personnel_id' => $note->personnel_id,
            'channel' => $this->enumValue($note->channel) ?? MeetingChannel::Phone->value,
            'subject' => filled($note->next_action) ? Str::limit(Str::squish((string) $note->next_action), 200, '') : __('meeting_plan.values.follow_up_subject'),
        ];

        if (! $follow instanceof MeetingPlan) {
            parent::create([
                ...$values,
                'planned_on' => $date,
                'status' => MeetingPlanStatus::Planned->value,
                'source' => MeetingPlanSource::FollowUp->value,
                'follow_up_note_id' => $note->getKey(),
            ]);

            return;
        }

        if ($follow->status === MeetingPlanStatus::Done) {
            return;
        }

        // Tarih degistiyse iptal edilmis sonraki adim yeniden planlanir.
        if ($follow->planned_on?->toDateString() !== $date) {
            $values['planned_on'] = $date;
            $values['status'] = MeetingPlanStatus::Planned->value;
        }

        $follow->fill($values);
        $changes = $this->saveWithoutVersion($follow);

        if ($changes !== []) {
            $this->recordActivity($follow, 'updated', $changes);
        }
    }

    /** Sonuc notu silinen plan yeniden planliya doner. */
    private function reopen(MeetingPlan $plan): void
    {
        $plan->fill(['status' => MeetingPlanStatus::Planned->value, 'meeting_note_id' => null, 'completed_at' => null]);
        $changes = $this->saveWithoutVersion($plan);

        if ($changes !== []) {
            $this->recordActivity($plan, 'reopened', $changes);
        }
    }

    private function subjectFor(PartyMeetingNote $note): ?string
    {
        $text = filled($note->subject) ? (string) $note->subject : (string) $note->note;

        return $text === '' ? null : Str::limit(Str::squish($text), 200, '');
    }

    /**
     * Form verisinden katilimci listesini ayirir; anahtar yoksa null (dokunulmaz).
     *
     * @param  array<string, mixed>  $data
     * @return list<int>|null
     */
    private function pullParticipants(array &$data): ?array
    {
        if (! array_key_exists('participant_ids', $data)) {
            return null;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $data['participant_ids']), fn (int $id): bool => $id > 0));
        unset($data['participant_ids']);

        return $ids;
    }

    /**
     * Katilimcilari tam listeye esitler; sorumlu personel katilimci sayilmaz.
     *
     * @param  list<int>  $ids
     */
    private function syncParticipants(MeetingPlan $plan, array $ids): void
    {
        $ids = array_values(array_diff(array_unique($ids), [(int) $plan->personnel_id]));
        $plan->participantRows()->whereNotIn('personnel_id', $ids === [] ? [0] : $ids)->delete();
        $this->ensureParticipants($plan, $ids);
        $plan->unsetRelation('participants');
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return filled($value) ? (string) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return $record instanceof MeetingPlan
            ? array_filter([
                'taraf' => $record->party?->display_name,
                'tarih' => $record->planned_on?->format('d.m.Y'),
                'durum' => $record->status?->getLabel(),
                'konu' => $record->subject,
            ], fn ($value): bool => $value !== null)
            : [];
    }
}
