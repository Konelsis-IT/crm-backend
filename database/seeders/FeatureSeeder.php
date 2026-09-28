<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Console\Commands\ExportFeaturesCommand;
use App\Query\Platform\FeatureQueries;
use App\Services\Platform\PlatformFeatureService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;

/**
 * Ozellik anahtarlari (B39, D-128): katalogu `features` tablosuna yazar ve
 * database/seeders/data/features.php dosyasindaki acik / kapali durumlari
 * uygular. Canliya aktarim yolu:
 *
 *   1. Yerelde ozellikleri veritabanindan acip kapatin.
 *   2. `php artisan konelsis:features:export` dosyayi uretir; dosya depoya girer.
 *   3. Canlida B39 uygulandiktan sonra DBA bu seeder'i calistirir:
 *      `php artisan db:seed --class=FeatureSeeder` (sema korumasi izni ile).
 *
 * Tekrar calistirilabilir: eksik satir eklenir, tanimlar tazelenir, dosyada
 * yazan durum uygulanir; dosyada olmayan ozellik varsayilan durumunda kalir.
 * Seeder calistirilmasa da uygulama ilk istekte satirlari varsayilan
 * durumlariyla (surum notlari kapali, digerleri acik) kendisi yazar.
 */
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B39')) {
            $this->command?->warn('B39 (features tablosu) uygulanmamis; ozellik anahtarlari atlandi.');

            return;
        }

        $queries = app(FeatureQueries::class);
        $features = app(PlatformFeatureService::class);

        $rows = $queries->all();

        if ($features->outOfSync($rows)) {
            $features->syncCatalog($rows);
        }

        $path = database_path(ExportFeaturesCommand::DATA_FILE);
        $states = is_file($path) ? require $path : [];

        if (! is_array($states) || $states === []) {
            $this->command?->info('Ozellik katalogu yazildi; veri dosyasi yok, varsayilan durumlar gecerli.');

            return;
        }

        $result = $features->applyStates(array_map(fn ($value): bool => (bool) $value, $states));

        $this->command?->info(sprintf('Ozellik anahtarlari: %d durum degisti.', $result['changed']));

        if ($result['skipped'] !== []) {
            $this->command?->warn('Katalogda olmayan kodlar atlandi: '.implode(', ', $result['skipped']));
        }
    }
}
