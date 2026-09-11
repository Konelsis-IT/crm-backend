<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\ActorContext;
use App\Services\Notification\DeadlineScanner;
use Illuminate\Console\Command;

/**
 * Yaklasan / gecmis son tarihleri tarar, is uyarisi acar ve sahiplerini
 * bildirir (D-82). Zamanlayici her sabah calistirir; hareket kaydinda
 * personel bos kalir ("Sistem").
 */
final class ScanDeadlinesCommand extends Command
{
    protected $signature = 'konelsis:deadlines:scan';

    protected $description = 'Yaklasan ve gecmis son tarihler icin is uyarisi acar, sahiplerine bildirim gonderir.';

    public function handle(DeadlineScanner $scanner, ActorContext $actor): int
    {
        $result = $actor->runAsSystem(fn (): array => $scanner->scan());

        $this->info(sprintf(
            '%d son tarih bulundu; %d yeni uyari acildi, %d bildirim gonderildi, %d uyari kapandi.',
            $result['hits'],
            $result['opened'],
            $result['notified'],
            $result['closed'],
        ));

        return self::SUCCESS;
    }
}
