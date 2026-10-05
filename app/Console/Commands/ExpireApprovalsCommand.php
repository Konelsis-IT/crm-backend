<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Platform\Feature;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Console\Command;

/**
 * SLA'si gecen onay adimlarini "suresi doldu" yapar ve escalation zincirini
 * bildirir (SM-APR expired; D-35). Zamanlayici on bes dakikada bir calistirir;
 * hareket kaydinda personel bos kalir ("Sistem").
 */
final class ExpireApprovalsCommand extends Command
{
    protected $signature = 'konelsis:approvals:expire';

    protected $description = 'SLA suresi gecen onay taleplerini kapatir ve escalation bildirimi gonderir.';

    public function handle(ApprovalRequestService $requests, ActorContext $actor): int
    {
        if (! SchemaReadiness::hasBatch('B07')) {
            $this->info('B07 uygulanmadi; atlandi.');

            return self::SUCCESS;
        }

        // Ozellik kapaliysa (ya da surumu canlida yayinlanmadiysa, D-151) islem yapilmaz.
        if (! FeatureFlags::enabled(Feature::Approvals)) {
            $this->info('Onay ozelligi kapali; atlandi.');

            return self::SUCCESS;
        }

        $count = $actor->runAsSystem(fn (): int => $requests->expireOverdue());

        $this->info(sprintf('%d onay talebinin suresi doldu.', $count));

        return self::SUCCESS;
    }
}
