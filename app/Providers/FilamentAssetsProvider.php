<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

/**
 * Konelsis'e ozel panel varliklari. `php artisan filament:assets` ile
 * public/css/konelsis/ ve public/js/konelsis/ altina yayimlanir.
 *
 * - konelsis.css (D-69): kucuk, yeniden kullanilabilir stil ekleri.
 * - react / react-dom 18.3.1 (UMD, resources/js/vendor/react) ve
 *   konelsis-chat.js (D-83): kurum ici sohbet arayuzu. `loadedOnRequest()`:
 *   her sayfaya degil, yalniz sohbet baslaticisi (launcher.blade.php)
 *   yerlestirildiginde yuklenir.
 *
 * Kural: Filament'in yerlesik bilesenleri yeterli olmadiginda, kullanici onayiyla
 * ve yalniz yeniden kullanilabilir kucuk duzeltmeler icin (AGENTS.md).
 */
final class FilamentAssetsProvider extends ServiceProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('konelsis', resource_path('css/filament/konelsis.css')),
            Js::make('react', resource_path('js/vendor/react/react.production.min.js'))->loadedOnRequest(),
            Js::make('react-dom', resource_path('js/vendor/react/react-dom.production.min.js'))->loadedOnRequest(),
            Js::make('konelsis-chat', resource_path('js/chat/konelsis-chat.js'))->loadedOnRequest(),
        ], 'konelsis');
    }
}
