<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Yerel gelistirmede dosya yuklemenin calismasini saglar.
 *
 * Windows'ta php.ini icindeki upload_tmp_dir bos birakildiginda PHP, gelen
 * dosya icin gecici dosya olusturamaz; her yukleme "failed to upload" ile
 * biter ve gunluge hicbir sey yazilmaz. Ayar PHP_INI_SYSTEM oldugu icin
 * calisma zamaninda degistirilemez, makine geneli php.ini ise yonetici
 * yetkisi ister.
 *
 * Cozum: proje icindeki .php-ini klasoru ek ayar dizini olarak gosterilir ve
 * yerlesik sunucuya aktarilir. Boylece "php artisan serve" hicbir ek adim
 * gerektirmeden calisir ve gecici yuklemeler storage/app/upload-tmp altinda
 * tutulur.
 */
class LocalUploadSupportProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->runningInConsole() || ! $this->app->environment('local')) {
            return;
        }

        $scanDirectory = base_path('.php-ini');

        if (! is_dir($scanDirectory)) {
            return;
        }

        $this->ensureTemporaryUploadDirectory();

        // Yerlesik sunucu, ayarlari yalniz bu dizinden okur.
        putenv('PHP_INI_SCAN_DIR='.$scanDirectory);
        $_ENV['PHP_INI_SCAN_DIR'] = $scanDirectory;
        $_SERVER['PHP_INI_SCAN_DIR'] = $scanDirectory;

        // artisan serve, cocuk surece yalniz izinli degiskenleri gecirir.
        // PHP_INI_SCAN_DIR yukaridaki ayar dosyasi icin gereklidir; TMP, TEMP
        // ve USERPROFILE ise silindiginde Windows gecici klasoru C:\Windows
        // olarak cozuluyor ve yazilamiyor. Ucunu de geri veriyoruz.
        foreach (['PHP_INI_SCAN_DIR', 'TMP', 'TEMP', 'USERPROFILE'] as $variable) {
            if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $variable;
            }
        }
    }

    /** Gecici yukleme klasoru yoksa olusturur. */
    private function ensureTemporaryUploadDirectory(): void
    {
        $path = storage_path('app/upload-tmp');

        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }
}
