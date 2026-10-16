<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Platform\Feature;
use App\Services\Audit\ActorContext;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\RecurringWorkService;
use Illuminate\Console\Command;

/**
 * Gorev ve sorumluluklarin donem isleri (D-219): acilis gunu gelen her tanim
 * icin sorumlunun panosuna donemin isi acilir. Zamanlayici her sabah calistirir;
 * tekrar calistirmak zararsizdir (ayni donemin ikinci isi acilmaz) ve kacan
 * gun sonraki calismada tamamlanir.
 */
final class OpenRecurringWorksCommand extends Command
{
    protected $signature = 'konelsis:recurring-works';

    protected $description = 'Gorev ve sorumluluklarin (tekrarli islerin) bu donemki islerini sorumlularin panosuna acar.';

    public function handle(RecurringWorkService $service, ActorContext $actor): int
    {
        if (! SchemaReadiness::hasBatch('B57') || ! FeatureFlags::enabled(Feature::WorkRecurring)) {
            $this->info('Gorev ve sorumluluklar kapali ya da B57 uygulanmadi; atlandi.');

            return self::SUCCESS;
        }

        $opened = $actor->runAsSystem(fn (): int => $service->openDue());

        $this->info(sprintf('%d donem isi acildi.', $opened));

        return self::SUCCESS;
    }
}
