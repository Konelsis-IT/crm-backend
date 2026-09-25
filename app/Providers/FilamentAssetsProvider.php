<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Support\Assets\KonelsisCss as Css;
use App\Filament\Support\Assets\KonelsisJs as Js;
use App\Models\Personnel\Personnel;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

/**
 * Konelsis'e ozel panel varliklari. `php artisan filament:assets` ile
 * public/css/konelsis/ ve public/js/konelsis/ altina yayimlanir (yayimlanan
 * dosya adi kaynak dosyanin degil, asagidaki kimligin adidir).
 *
 * - konelsis.css (D-69): kucuk, yeniden kullanilabilir stil ekleri; her panel
 *   sayfasinda yuklenir.
 * - react / react-dom 18.3.1 (UMD, resources/js/vendor/react) ve
 *   konelsis-chat.js (D-83): kurum ici sohbet arayuzu. `loadedOnRequest()`:
 *   her sayfaya degil, yalniz sohbet baslaticisi (launcher.blade.php)
 *   yerlestirildiginde yuklenir.
 * - konelsis-social.css ve dokuz social-*.js (D-106, B31): Sosyal Medya
 *   sayfasinin React uygulamasi. Hepsi `loadedOnRequest()`: stil
 *   resources/views/filament/social/app.blade.php, betikler
 *   resources/views/filament/social/scripts.blade.php tarafindan yalniz o
 *   sayfada cagrilir. Betik sirasi scripts.blade.php'de sabittir (core ilk,
 *   app son). React'in ikinci kopyasi yoktur; sohbet yuklemiyorsa ayni
 *   react / react-dom varliklari kullanilir (App\Filament\Support\ReactRuntime).
 * - social-calendar.js (B34, D-109): ay takvimi izgarasi sosyal medya plani ile
 *   gorusme plani arasinda ortaktir; meeting-calendar.js gorusme plani
 *   takvimini ayni cekirdek (social-core) ve ayni stil ile cizer
 *   (resources/views/filament/meetings/scripts.blade.php).
 *
 * - konelsis-work.css ve work-*.js (B36, D-115): Is panosu, kontrol matrisi,
 *   analiz panosu ve personel kartindaki Dikkat karti (React). Hepsi
 *   `loadedOnRequest()`: resources/views/filament/work/app.blade.php ve
 *   attention-card.blade.php stili, filament.work.scripts betikleri yukler.
 *
 * Surum: adres eki (?v=) yayimlanan dosyanin icerik ozetidir (KonelsisCss /
 * KonelsisJs, 21 Eylul 2026); dosya degisince tarayici yenisini indirir.
 *
 * Kural: Filament'in yerlesik bilesenleri yeterli olmadiginda, kullanici onayiyla
 * (AGENTS.md). Kaynak dosya degisince `php artisan filament:assets` yeniden
 * calistirilir; yayimlanan kopyalar depoda izlenir.
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
            Css::make('konelsis-social', resource_path('css/filament/konelsis-social.css'))->loadedOnRequest(),
            Js::make('social-core', resource_path('js/social/social-core.js'))->loadedOnRequest(),
            Js::make('social-editor', resource_path('js/social/social-editor.js'))->loadedOnRequest(),
            Js::make('social-feed', resource_path('js/social/social-feed.js'))->loadedOnRequest(),
            Js::make('social-detail', resource_path('js/social/social-detail.js'))->loadedOnRequest(),
            Js::make('social-composer', resource_path('js/social/social-composer.js'))->loadedOnRequest(),
            Js::make('social-calendar', resource_path('js/social/social-calendar.js'))->loadedOnRequest(),
            Js::make('social-planner', resource_path('js/social/social-planner.js'))->loadedOnRequest(),
            Js::make('social-insights', resource_path('js/social/social-insights.js'))->loadedOnRequest(),
            Js::make('social-manage', resource_path('js/social/social-manage.js'))->loadedOnRequest(),
            Js::make('social-app', resource_path('js/social/social-app.js'))->loadedOnRequest(),
            Js::make('meeting-calendar', resource_path('js/meetings/meeting-calendar.js'))->loadedOnRequest(),
            // Is panosu (B36, D-115): cekirdek + dort ekran; yalniz ilgili sayfada.
            Css::make('konelsis-work', resource_path('css/filament/konelsis-work.css'))->loadedOnRequest(),
            Js::make('work-core', resource_path('js/work/work-core.js'))->loadedOnRequest(),
            Js::make('work-board', resource_path('js/work/work-board.js'))->loadedOnRequest(),
            Js::make('work-matrix', resource_path('js/work/work-matrix.js'))->loadedOnRequest(),
            Js::make('work-analysis', resource_path('js/work/work-analysis.js'))->loadedOnRequest(),
            Js::make('work-attention', resource_path('js/work/work-attention.js'))->loadedOnRequest(),
            // Masaustu (Windows) bildirimi + bildirim sesi (D-126, kullanici onayi
            // 25 Eylul 2026): her panel sayfasinda; ayarlari AlertFeed verir.
            Js::make('konelsis-alerts', resource_path('js/konelsis-alerts.js')),
        ], 'konelsis');

        // Betik ayarlari istek aninda (oturum, dil ve adres hazir): window.filamentData.konelsisAlerts.
        Filament::serving(function (): void {
            $user = auth()->user();

            if (! $user instanceof Personnel || ! $user->isActive()) {
                return;
            }

            FilamentAsset::registerScriptData([
                'konelsisAlerts' => [
                    'feed' => route('filament.admin.notifications.feed'),
                    'user' => (int) $user->getKey(),
                    'poll' => 20000,
                    'icon' => asset('images/konelsis-favicon.png'),
                    'labels' => [
                        'app' => (string) __('app.name'),
                        'enabled' => (string) __('alerts.messages.enabled'),
                        'denied' => (string) __('alerts.messages.denied'),
                        'unsupported' => (string) __('alerts.messages.unsupported'),
                    ],
                ],
            ]);
        });
    }
}
