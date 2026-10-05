<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Query\Platform\FeatureReleaseQueries;
use App\Services\Platform\FeatureReleaseService;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Surum yayini (D-151, 2 Ekim 2026 kullanici karari): kod canliya dogrudan
 * gider; yeni ozellikler surumleri yayinlanana kadar gorunmez.
 *
 *   php artisan konelsis:release            -> yalniz durumu gosterir (yazmaz)
 *   php artisan konelsis:release 2.4        -> 2.4'u yayinlar
 *   php artisan konelsis:release 2.3 --geri-al  -> eski surume doner
 *
 * Yayin, `feature_releases` tablosuna bir satir ekler (B42) ve Personel
 * Hareketleri'ne "Sistem" olarak yazilir. Onceki yayindan bu yana gelen
 * surumlerin kapali ozellikleri acilir (D-153; ilk yayinda yalniz o surumunkiler).
 * Yerel ortamda calistirilmaz: yayin kaydi olmayan ortamda butun ozellikler gorunur.
 */
final class PublishReleaseCommand extends Command
{
    protected $signature = 'konelsis:release
        {surum? : Yayinlanacak surum (orn. 2.4); bos birakilirsa yalniz durum gosterilir}
        {--not= : Yayin notu (en fazla 500 karakter)}
        {--geri-al : Canlidakinden eski bir surume donmeye izin verir}';

    protected $description = 'Canlida surum yayinlar (D-151): o surume kadar olan ozellikler gorunur olur. Surum verilmezse yalniz durumu gosterir.';

    public function handle(FeatureReleaseQueries $releases, FeatureReleaseService $service): int
    {
        $version = trim((string) $this->argument('surum'));

        if ($version === '') {
            $this->status($releases->currentVersion());

            return self::SUCCESS;
        }

        if (! SchemaReadiness::hasBatch('B42')) {
            $this->error('B42 (ozellik surumleri ve yayin kaydi) bu veritabaninda uygulanmamis; once DBA uygulamali.');

            return self::FAILURE;
        }

        try {
            $release = $service->publish($version, $this->option('not'), (bool) $this->option('geri-al'));
        } catch (AbstractException $exception) {
            $this->error($exception->userMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('%s yayinlandi (%s).', $release->version, DisplayTime::format($release->published_at)));

        // D-153: bu guncellemeyle gelen kapali ozellikler acildi.
        $opened = array_map(
            static fn (string $code): string => Feature::tryFrom($code)?->title() ?? $code,
            $service->openedFeatures(),
        );
        $this->line($opened === []
            ? 'Acilan ozellik yok (hepsi zaten acikti ya da bu surumle gelen ozellik yok).'
            : sprintf('Acilan ozellikler (%d): %s', count($opened), implode(', ', $opened)));

        $this->status((string) $release->version);

        return self::SUCCESS;
    }

    /** Surume gore ozellikler: yayinda mi, bekliyor mu. Yalniz okur. */
    private function status(?string $published): void
    {
        if (! SchemaReadiness::hasBatch('B42')) {
            $this->warn('B42 uygulanmamis: yayin kaydi tutulamaz, butun ozellikler surumden bagimsiz gorunur.');
        } elseif ($published === null) {
            $this->warn('Yayin kaydi yok: butun ozellikler surumden bagimsiz gorunur (yerel ortam ya da ilk yayindan once).');
        } else {
            $this->info("Canlidaki yayin surumu: {$published}");
        }

        $groups = [];

        foreach (Feature::cases() as $feature) {
            $groups[$feature->version()][] = $feature->title();
        }

        uksort($groups, fn (string $a, string $b): int => version_compare($a, $b));

        $rows = [];

        foreach ($groups as $version => $titles) {
            $rows[] = [
                $version,
                $published === null || version_compare((string) $version, $published, '<=') ? 'yayinda' : 'bekliyor',
                count($titles),
                Str::limit(implode(', ', $titles), 110),
            ];
        }

        $this->table(['Surum', 'Durum', 'Ozellik', 'Ozellikler'], $rows);
    }
}
