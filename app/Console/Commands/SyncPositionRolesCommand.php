<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\ActorContext;
use App\Services\Authorization\PositionRoleSync;
use Illuminate\Console\Command;

/**
 * Mevcut pozisyonlar icin rol uretir ve acik atama sahiplerine verir (D-81).
 * B03A uygulandiktan sonra bir kez calistirilir; sonrasi servislerde otomatiktir.
 */
final class SyncPositionRolesCommand extends Command
{
    protected $signature = 'konelsis:roles:sync-positions';

    protected $description = 'Her pozisyon icin rol olusturur ve pozisyon sahiplerine atar.';

    public function handle(PositionRoleSync $sync, ActorContext $actor): int
    {
        if (! $sync->isReady()) {
            $this->info('B03A/B05 uygulanmadi; atlandi.');

            return self::SUCCESS;
        }

        $result = $actor->runAsSystem(fn (): array => $sync->syncAll());

        $this->info(sprintf('%d pozisyon esitlendi, %d personele rol verildi.', $result['positions'], $result['granted']));

        return self::SUCCESS;
    }
}
