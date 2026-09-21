<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\ActorContext;
use App\Services\Party\MeetingReminderScanner;
use Illuminate\Console\Command;

/**
 * Gorusme plani ve sonraki adim hatirlatmasi (B34, D-109): yarin ve bugun
 * planli gorusmeler icin sorumlu ve katilan personele zil bildirimi.
 * Zamanlayici her sabah calistirir; tekrar calistirmak zararsizdir (ayni
 * asama icin ikinci bildirim gitmez).
 */
final class RemindMeetingPlansCommand extends Command
{
    protected $signature = 'konelsis:meetings:remind';

    protected $description = 'Yarin ve bugun planli gorusmeler (sonraki adimlar dahil) icin sorumlu ve katilan personele zil hatirlatmasi gonderir.';

    public function handle(MeetingReminderScanner $scanner, ActorContext $actor): int
    {
        $result = $actor->runAsSystem(fn (): array => $scanner->scan());

        $this->info(sprintf(
            '%d planli gorusme bulundu; %d bildirim gonderildi, %d zaten hatirlatilmisti.',
            $result['plans'],
            $result['sent'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
