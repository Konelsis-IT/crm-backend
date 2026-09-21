<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Party\MeetingPlan;
use App\Models\Party\PartyMeetingNote;
use App\Services\Party\MeetingPlanService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;

/**
 * Var olan gorusme notlarini gorusme planina yansitir (B34, D-109).
 *
 * B34'ten once yazilmis notlar (firma takip listesi aktarimi, elle girilen
 * notlar) plana kendiliginden dusmez; bu seeder her not icin "gerceklesti"
 * satirini, tarihli sonraki adimi icin planli satiri acar
 * (MeetingPlanService::syncFromNote). Idempotent: zaten yansimis nota
 * dokunmaz. Kalici uretim verisidir; uretimde de calisir.
 */
class MeetingPlanBackfillSeeder extends Seeder
{
    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B28') || ! SchemaReadiness::hasBatch('B34')) {
            $this->command?->warn('Gorusme notu (B28) ya da gorusme plani (B34) tablolari yok; MeetingPlanBackfillSeeder atlandi.');

            return;
        }

        $service = app(MeetingPlanService::class);
        $synced = 0;

        PartyMeetingNote::query()
            ->whereNotIn('id', MeetingPlan::query()->whereNotNull('meeting_note_id')->select('meeting_note_id'))
            ->orderBy('id')
            ->chunkById(200, function ($notes) use ($service, &$synced): void {
                foreach ($notes as $note) {
                    $service->syncFromNote($note);
                    $synced++;
                }
            });

        // Sonuc satiri olup sonraki adimi yansimamis notlar (tarih sonradan eklendiyse).
        PartyMeetingNote::query()
            ->whereNotNull('next_action_on')
            ->whereNotIn('id', MeetingPlan::query()->whereNotNull('follow_up_note_id')->select('follow_up_note_id'))
            ->orderBy('id')
            ->chunkById(200, function ($notes) use ($service, &$synced): void {
                foreach ($notes as $note) {
                    $service->syncFromNote($note);
                    $synced++;
                }
            });

        $this->command?->info(sprintf('Gorusme plani: %d gorusme notu plana yansitildi.', $synced));
    }
}
