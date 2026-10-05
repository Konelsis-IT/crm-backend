<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Platform\Feature;
use App\Query\Platform\FeatureQueries;
use App\Services\Platform\PlatformFeatureService;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Ozellik anahtarlarini (D-128) canliya tasimak icin veri dosyasi uretir.
 *
 * Yerelde `features` tablosunda acip kapattiginiz durumlar
 * database/seeders/data/features.php dosyasina yazilir; canlida FeatureSeeder
 * bu dosyayi yalniz yeni eklenen ozelliklerin ilk durumu icin uygular (D-151:
 * canlida acilip kapatilmis ozelliklere dokunmaz). Dosyadaki her satir
 * `kod => acik mi` ve yaninda ozelligin adidir; elle de duzenlenebilir.
 *
 * Komut yalniz okur ve dosya yazar; tek istisna, tablo katalogla esit
 * degilse once eksik satirlari ekler (uygulamanin ilk istekte yaptigi
 * esitlemenin aynisi; `is_active` degerine dokunmaz).
 */
final class ExportFeaturesCommand extends Command
{
    protected $signature = 'konelsis:features:export';

    protected $description = 'Ozellik anahtarlarinin acik / kapali durumunu canliya aktarim dosyasina yazar.';

    public const DATA_FILE = 'seeders/data/features.php';

    public function handle(FeatureQueries $queries, PlatformFeatureService $features): int
    {
        if (! SchemaReadiness::hasBatch('B39')) {
            $this->error('B39 (features tablosu) uygulanmamis; aktarilacak durum yok.');

            return self::FAILURE;
        }

        $rows = $queries->all();

        if ($features->outOfSync($rows)) {
            $features->syncCatalog($rows);
            $rows = $queries->all();
        }

        $lines = [];
        $off = [];

        foreach (Feature::cases() as $feature) {
            $row = $rows->get($feature->value);
            $active = $row === null ? $feature->defaultActive() : (bool) $row->is_active;

            if (! $active) {
                $off[] = $feature->value;
            }

            $lines[] = sprintf("    %s => %s, // %s", var_export($feature->value, true), $active ? 'true' : 'false', $feature->title());
        }

        $stamp = DisplayTime::format(Carbon::now('UTC'), 'd.m.Y H:i');
        $content = "<?php\n\n"
            ."// Ozellik anahtarlari (D-128): FeatureSeeder bu dosyadaki durumlari canlidaki\n"
            ."// `features` tablosuna uygular. true = acik, false = kapali. Katalog sirasi.\n"
            ."// Uretim: php artisan konelsis:features:export ({$stamp}, yerel veritabani).\n"
            ."// Elle de duzenlenebilir; katalogda olmayan kod atlanir.\n\n"
            ."return [\n".implode("\n", $lines)."\n];\n";

        $path = database_path(self::DATA_FILE);
        file_put_contents($path, $content);

        $this->info(sprintf('%d ozellik yazildi (%d kapali): %s', count($lines), count($off), $path));

        if ($off !== []) {
            $this->line('Kapali: '.implode(', ', $off));
        }

        return self::SUCCESS;
    }
}
