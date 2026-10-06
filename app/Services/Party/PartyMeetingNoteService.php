<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Party\MeetingNoteProposalMismatchException;
use App\Exceptions\RecordArchivedException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Party\PartyMeetingNote;
use App\Services\AbstractService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gorusme notu servisi (B28, D-98).
 *
 * Tarih bos birakilirsa bugunun tarihi (UTC), gorusen personel bos
 * birakilirsa oturumdaki personel yazilir. Geri kalan islemler
 * AbstractService'ten gelir; liste en yeni gorusmeden baslar.
 *
 * Gorusme plani (B34, D-109): her not plana "gerceklesti" satiri olarak,
 * tarihli sonraki adimi planli satir olarak yansir; hatirlatma bu satir
 * uzerinden gider. Not bir planin sonucu olarak yaziliyorsa
 * (PLAN_CONTEXT) yeni satir acilmaz, o plan nota baglanir.
 *
 * Arsiv (B44, D-156: "Projede silme islemi yok ... arsive alinabilmeli"):
 * not silinmez, archive() ile arsive alinir; ondan dogan plan satirlari ayni
 * anla arsivlenir (MeetingPlanService::archiveForNote). restore() ikisini
 * birlikte geri alir. Arsivdeki not duzenlenemez.
 */
final class PartyMeetingNoteService extends AbstractService
{
    /** create() verisinde: notu sonucu olarak yazan gorusme planinin kimligi. */
    public const PLAN_CONTEXT = '_meeting_plan_id';

    protected string $model = PartyMeetingNote::class;

    /** @var list<string> */
    protected array $with = ['contact', 'personnel'];

    protected string $orderBy = 'noted_on';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data  B41: business_case_id, proposal_ids (ayni potansiyel isin teklifleri)
     */
    public function create(array $data): Model
    {
        $planId = isset($data[self::PLAN_CONTEXT]) ? (int) $data[self::PLAN_CONTEXT] : null;
        unset($data[self::PLAN_CONTEXT]);

        if (blank($data['noted_on'] ?? null)) {
            $data['noted_on'] = Carbon::now('UTC')->toDateString();
        }

        if (blank($data['personnel_id'] ?? null)) {
            $data['personnel_id'] = app(ActorContext::class)->personnelId();
        }

        return $this->transactions->run(function () use ($data, $planId): Model {
            [$data, $proposalIds] = $this->resolveDealLinks($data);

            /** @var PartyMeetingNote $note */
            $note = parent::create($data);

            $this->syncProposals($note, $proposalIds);
            app(MeetingPlanService::class)->syncFromNote($note, $planId);

            return $note;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var PartyMeetingNote $current */
            $current = $this->show($record);

            if ($current->isArchived()) {
                throw RecordArchivedException::make();
            }

            unset($data['archived_at']);
            $data['party_id'] ??= $current->party_id;

            if (! array_key_exists('business_case_id', $data) && array_key_exists('proposal_ids', $data)) {
                $data['business_case_id'] = $current->business_case_id;
            }

            [$data, $proposalIds] = $this->resolveDealLinks($data);
            $data['party_id'] = $current->party_id;

            /** @var PartyMeetingNote $note */
            $note = parent::update($record, $data);

            $this->syncProposals($note, $proposalIds);
            app(MeetingPlanService::class)->syncFromNote($note);

            return $note;
        });
    }

    /**
     * Potansiyel is ve teklif baglantisi (B41, D-137). Teklifler tek bir
     * potansiyel ise ait olmalidir; potansiyel is bos ise tekliflerinkinden
     * gelir, taraf bos ise potansiyel isin musterisinden. Grup uygulanmadiysa
     * baglanti anahtarlari yok sayilir. $proposalIds null = baglantilara dokunma.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<int>|null}
     */
    private function resolveDealLinks(array $data): array
    {
        $proposalIds = array_key_exists('proposal_ids', $data)
            ? array_values(array_unique(array_map('intval', array_filter((array) $data['proposal_ids']))))
            : null;
        unset($data['proposal_ids']);

        if (! SchemaReadiness::hasBatch('B41')) {
            unset($data['business_case_id']);

            return [$data, null];
        }

        $caseId = filled($data['business_case_id'] ?? null) ? (int) $data['business_case_id'] : null;

        if ($proposalIds !== null && $proposalIds !== []) {
            $caseIds = Proposal::query()->whereKey($proposalIds)->distinct()->pluck('business_case_id')->map(fn (mixed $id): int => (int) $id)->all();

            if (count($caseIds) !== 1 || ($caseId !== null && $caseIds[0] !== $caseId)) {
                throw MeetingNoteProposalMismatchException::make();
            }

            $caseId = $caseIds[0];
        }

        if (array_key_exists('business_case_id', $data) || $caseId !== null) {
            $data['business_case_id'] = $caseId;
        }

        if ($caseId !== null && blank($data['party_id'] ?? null)) {
            $data['party_id'] = BusinessCase::query()->whereKey($caseId)->value('primary_party_id');
        }

        if ($caseId === null && $proposalIds === null && array_key_exists('business_case_id', $data)) {
            $proposalIds = [];
        }

        return [$data, $proposalIds];
    }

    /** @param  list<int>|null  $proposalIds */
    private function syncProposals(PartyMeetingNote $note, ?array $proposalIds): void
    {
        if ($proposalIds === null) {
            return;
        }

        $actor = app(ActorContext::class)->personnelId();

        $note->proposals()->sync(array_fill_keys($proposalIds, ['created_by_personnel_id' => $actor]));
    }

    /**
     * Arayuzde silme yoktur (D-156); bu yol yalniz aktarim duzeltmelerinde
     * (seeder) kullanilir.
     */
    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var PartyMeetingNote $note */
            $note = $this->lockForUpdate($record);

            app(MeetingPlanService::class)->detachNote($note);

            return parent::delete($note);
        });
    }

    /**
     * Notu arsive alir (B44, D-156). Ondan dogan gorusme plani satirlari
     * (gerceklesti satiri ve gerceklesmemis sonraki adim) ayni anla arsivlenir;
     * boylece takvimde, listede ve hatirlatmalarda gorunmez. Yalniz archived_at
     * yazilir; kimin arsivledigi hareket kaydindadir (party_meeting_note.archived).
     */
    public function archive(Model|int|string $record): PartyMeetingNote
    {
        if (! SchemaReadiness::hasBatch('B44')) {
            throw InvalidTransitionException::make();
        }

        return $this->transactions->run(function () use ($record): PartyMeetingNote {
            /** @var PartyMeetingNote $note */
            $note = $this->lockForUpdate($record);

            if ($note->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $at = Carbon::now('UTC');

            $note->forceFill(['archived_at' => $at]);
            $this->saveWithoutVersion($note);
            $this->recordActivity($note, 'archived');

            app(MeetingPlanService::class)->archiveForNote($note, $at);

            return $note;
        });
    }

    /** Arsivdeki notu ve onunla birlikte arsivlenen plan satirlarini geri alir. */
    public function restore(Model|int|string $record): PartyMeetingNote
    {
        return $this->transactions->run(function () use ($record): PartyMeetingNote {
            /** @var PartyMeetingNote $note */
            $note = $this->lockForUpdate($record);

            if (! $note->isArchived()) {
                throw InvalidTransitionException::make();
            }

            /** @var Carbon $archivedAt */
            $archivedAt = $note->archived_at;

            $note->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($note);
            $this->recordActivity($note, 'restored');

            app(MeetingPlanService::class)->restoreForNote($note, $archivedAt);

            return $note;
        });
    }
}
