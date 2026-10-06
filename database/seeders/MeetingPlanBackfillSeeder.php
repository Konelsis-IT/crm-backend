<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Party\MeetingPlan;
use App\Models\Party\PartyMeetingNote;
use App\Services\Party\MeetingPlanService;
use App\Services\Platform\SchemaReadiness;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Var olan gorusme notlarini gorusme planina yansitir (B34, D-109).
 *
 * B34'ten once yazilmis notlar (firma takip listesi aktarimi, elle girilen
 * notlar) plana kendiliginden dusmez; bu seeder her not icin "gerceklesti"
 * satirini, tarihli sonraki adimi icin planli satiri acar
 * (MeetingPlanService::syncFromNote). Kalici uretim verisidir; uretimde de
 * calisir.
 *
 * D-165: korumali seeder. Yalniz plana HIC yansimamis notu (ne sonuc ne
 * sonraki adim satiri var) isler; syncFromNote o durumda yalniz yeni satir
 * acar. Kismen yansimis nota (biri var, digeri yok) dokunulmaz: syncFromNote
 * var olan plan satirini gunceller, bu yuzden yalniz uyarida sayilir. Var
 * olan plan ve not guncellenmez. Her not bir satirdir
 * (MeetingNotePersonnelSeeder::noteKey: 'note:<firma>|<tarih>|<metin ozeti>'),
 * islenince arsive duser.
 */
class MeetingPlanBackfillSeeder extends ProtectedSeeder
{
    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B28') || ! SchemaReadiness::hasBatch('B34')) {
            $this->command?->warn('Gorusme notu (B28) ya da gorusme plani (B34) tablolari yok; MeetingPlanBackfillSeeder atlandi.');

            return;
        }

        $service = app(MeetingPlanService::class);
        $synced = 0;

        // Plana hic yansimamis notlar: yalniz yeni satir acilir.
        PartyMeetingNote::query()
            ->with('party')
            ->whereNotIn('id', $this->resultNoteIds())
            ->whereNotIn('id', $this->followUpNoteIds())
            ->orderBy('id')
            ->chunkById(200, function ($notes) use ($service, &$synced): void {
                foreach ($notes as $note) {
                    $this->row(MeetingNotePersonnelSeeder::noteKey($note), function () use ($service, $note, &$synced): PartyMeetingNote {
                        $service->syncFromNote($note);
                        $synced++;

                        return $note;
                    });
                }
            });

        // D-165: kismen yansimis notlar (sonuc satiri yok ama sonraki adim
        // satiri var; ya da tarihli sonraki adimi yansimamis) guncelleme
        // gerektirir; seed bunlara dokunmaz, yalniz sayar.
        $partial = PartyMeetingNote::query()
            ->where(function (Builder $query): void {
                $query
                    ->where(fn (Builder $q): Builder => $q->whereNotIn('id', $this->resultNoteIds())->whereIn('id', $this->followUpNoteIds()))
                    ->orWhere(fn (Builder $q): Builder => $q->whereNotNull('next_action_on')->whereIn('id', $this->resultNoteIds())->whereNotIn('id', $this->followUpNoteIds()));
            })
            ->count();

        if ($partial > 0) {
            $this->command?->warn(sprintf('Gorusme plani: plana kismen yansimis %d nota dokunulmadi (var olan plan satiri guncellenmez, D-165).', $partial));
        }

        $this->command?->info(sprintf('Gorusme plani: %d gorusme notu plana yansitildi.', $synced));
    }

    /** @return Builder<MeetingPlan> */
    private function resultNoteIds(): Builder
    {
        return MeetingPlan::query()->whereNotNull('meeting_note_id')->select('meeting_note_id');
    }

    /** @return Builder<MeetingPlan> */
    private function followUpNoteIds(): Builder
    {
        return MeetingPlan::query()->whereNotNull('follow_up_note_id')->select('follow_up_note_id');
    }
}
