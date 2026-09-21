<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Models\Party\PartyMeetingNote;
use App\Services\AbstractService;
use App\Services\Audit\ActorContext;
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
     * @param  array<string, mixed>  $data
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
            /** @var PartyMeetingNote $note */
            $note = parent::create($data);

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
            /** @var PartyMeetingNote $note */
            $note = parent::update($record, $data);

            app(MeetingPlanService::class)->syncFromNote($note);

            return $note;
        });
    }

    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var PartyMeetingNote $note */
            $note = $this->lockForUpdate($record);

            app(MeetingPlanService::class)->detachNote($note);

            return parent::delete($note);
        });
    }
}
