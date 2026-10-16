<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Samples\PurchasingSampleSeeder;
use Illuminate\Console\Command;

/**
 * Yerel ornek veri (D-199, kullanici 13 Ekim 2026: "ornek veriler olustur ama
 * bunlar canliya gitmeyecek veriler olacak"). Yalniz APP_ENV=local ortaminda
 * calisir; deploy.sh ve DatabaseSeeder bu komutu ve database/seeders/Samples
 * altindaki siniflari hic cagirmaz.
 *
 *   php artisan konelsis:sample-data            ornek verileri kurar (bir kez)
 *   php artisan konelsis:sample-data --archive  ornek verileri arsive alir
 */
final class SampleDataCommand extends Command
{
    protected $signature = 'konelsis:sample-data {--archive : Örnek verileri arşive alır (silmez)}';

    protected $description = 'Yalniz yerel ortamda satin alma ornek verisini kurar ya da arsive alir; canliya gitmez.';

    public function handle(): int
    {
        if ($this->laravel->isProduction() || ! $this->laravel->environment('local')) {
            $this->error('Örnek veri yalnız yerel geliştirme ortamında kurulur (APP_ENV=local). Canlıda çalışmaz.');

            return self::FAILURE;
        }

        $seeder = $this->laravel->make(PurchasingSampleSeeder::class);
        $seeder->setCommand($this);
        $seeder->setContainer($this->laravel);

        if ((bool) $this->option('archive')) {
            $counts = $seeder->archive();
            $this->info('Örnek veriler arşive alındı: '.collect($counts)->map(static fn (int $count, string $what): string => $what.' '.$count)->implode(', ').'.');

            return self::SUCCESS;
        }

        $seeder->run();

        return self::SUCCESS;
    }
}
