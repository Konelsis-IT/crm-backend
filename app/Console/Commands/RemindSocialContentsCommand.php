<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Media\ChunkedUploadStore;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialReminderScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Paylasim gunu yaklasan sosyal medya icerikleri icin zil hatirlatmasi
 * gonderir (B31, D-106). Zamanlayici her sabah calistirir; elle de
 * calistirilabilir ve tekrar calistirmak zararsizdir (ayni asama icin ikinci
 * bildirim gitmez). Hareket kaydinda personel bos kalir ("Sistem").
 *
 * Ek olarak:
 * - Son calisma ani onbellege yazilir; Sosyal Medya sayfasi zamanlayici
 *   calismiyorsa yoneticiye bunu soyler (bootstrap `reminders.last_run_at`).
 * - Yarida kalmis parcali video yuklemelerinin gecici dosyalari temizlenir
 *   (yalniz social/tmp altinda; belge ya da medya dosyasi silinmez).
 */
final class RemindSocialContentsCommand extends Command
{
    protected $signature = 'konelsis:social:remind';

    protected $description = 'Paylasim gunu yaklasan sosyal medya icerikleri icin hazirlayana ve sorumlulara zil hatirlatmasi gonderir.';

    public function handle(SocialReminderScanner $scanner, ActorContext $actor): int
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            $this->info('B31 uygulanmadi; atlandi.');

            return self::SUCCESS;
        }

        $result = $actor->runAsSystem(fn (): array => $scanner->scan());

        Cache::forever(SocialReminderScanner::CACHE_LAST_RUN, Carbon::now('UTC')->toIso8601String());

        $this->info(sprintf(
            '%d aday icerik; %d icerik icin hatirlatma gerekti, %d aliciya %d bildirim gonderildi, %d icerik isaretlendi.',
            $result['candidates'],
            $result['due'],
            $result['recipients'],
            $result['notified'],
            $result['marked'],
        ));

        $this->purgeStaleUploads();

        return self::SUCCESS;
    }

    /** Gecici yukleme temizligi hatirlatmayi basarisiz kilmaz. */
    private function purgeStaleUploads(): void
    {
        $hours = max(1, (int) config('konelsis.social_media.tmp_ttl_hours', 24));

        try {
            $purged = app(ChunkedUploadStore::class)->purgeStale($hours);

            if (is_int($purged)) {
                $this->info(sprintf('%d yarim kalmis yukleme temizlendi.', $purged));
            }
        } catch (Throwable $exception) {
            $this->warn('Gecici yukleme temizligi yapilamadi: '.$exception->getMessage());
        }
    }
}
