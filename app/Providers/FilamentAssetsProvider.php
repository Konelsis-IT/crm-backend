<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Platform\FeatureFlags;
use App\Enums\Platform\Feature;
use App\Filament\Support\Assets\KonelsisCss as Css;
use App\Filament\Support\Assets\KonelsisJs as Js;
use App\Filament\Support\RecordHeading;
use App\Models\Personnel\Personnel;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
 *   work-quick.js (D-233): Is panosunun "Hizli is ekle" penceresi; panodan once yuklenir.
 * - konelsis-checklist.css ve checklist-board.js (D-157): potansiyel is
 *   sihirbazindaki teklif oncesi kontrol listesi tahtasi (React) ve Kaydet /
 *   Ileri ozeti. `loadedOnRequest()`: filament.acquisition.checklist-scripts
 *   yalniz potansiyel is olustur / duzenle sayfalarinda yukler.
 *
 * - konelsis-dash.css ve dash-*.js (D-173): UI Deneme'deki departman panolari
 *   ve pano bilesen katalogu (React). `loadedOnRequest()`:
 *   resources/views/filament/dashboards/app.blade.php stili,
 *   filament.dashboards.scripts betikleri yalniz o iki sayfada yukler.
 *   cost-lab.js (D-187): UI Deneme > Maliyet kalemleri; ayni cekirdek ve stil.
 *
 * - konelsis-nd.css ve nd-app.js (D-207): UI Deneme > Yeni pano (React, kendi
 *   bilesenleri; dash-* kullanmaz). `loadedOnRequest()`:
 *   resources/views/filament/new-dashboard/app.blade.php stili,
 *   filament.new-dashboard.scripts betigi yalniz o sayfada yukler.
 *
 * - report-copy.js (D-167): raporun bicimli metnini panoya zengin metin + duz
 *   metin olarak yazar (ReportActions::copy). Her panel sayfasinda (kucuk).
 *
 * - konelsis-purchasing.css (D-192): kalemli satin alma formlarinin kalem
 *   listesi (kesik cizgili "Kalem ekle", kalem basligi rozetleri) ve satin alma
 *   tekliflerinin karsilastirma matrisi. Her panel sayfasinda (kucuk).
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
            // Satin alma kalem listesi rozetleri ve teklif karsilastirma matrisi
            // (D-192, kullanici onayi 12 Ekim 2026); kucuk, her panel sayfasinda.
            Css::make('konelsis-purchasing', resource_path('css/filament/konelsis-purchasing.css')),
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
            // D-233: Is panosundaki "Hizli is ekle" penceresi (rozetli tek metin; kullanici onayi 21 Ekim 2026).
            Js::make('work-quick', resource_path('js/work/work-quick.js'))->loadedOnRequest(),
            Js::make('work-board', resource_path('js/work/work-board.js'))->loadedOnRequest(),
            // D-240: Isler listesi (React; Akilli is ekle acikken).
            Js::make('work-list', resource_path('js/work/work-list.js'))->loadedOnRequest(),
            // D-240: UI Deneme > Rapor yazma denemesi.
            Js::make('report-lab', resource_path('js/work/report-lab.js'))->loadedOnRequest(),
            Js::make('work-matrix', resource_path('js/work/work-matrix.js'))->loadedOnRequest(),
            Js::make('work-analysis', resource_path('js/work/work-analysis.js'))->loadedOnRequest(),
            Js::make('work-attention', resource_path('js/work/work-attention.js'))->loadedOnRequest(),
            // Teklif oncesi kontrol listesi tahtasi (D-157): yalniz potansiyel is olustur / duzenle.
            Css::make('konelsis-checklist', resource_path('css/filament/konelsis-checklist.css'))->loadedOnRequest(),
            Js::make('checklist-board', resource_path('js/acquisition/checklist-board.js'))->loadedOnRequest(),
            // Departman panolari (D-173, kullanici onayi 8 Ekim 2026: "tamamen React ile"):
            // yalniz UI Deneme > Departman panolari ve Pano bilesenleri sayfalarinda.
            Css::make('konelsis-dash', resource_path('css/filament/konelsis-dash.css'))->loadedOnRequest(),
            Js::make('dash-core', resource_path('js/dashboards/dash-core.js'))->loadedOnRequest(),
            Js::make('dash-widgets', resource_path('js/dashboards/dash-widgets.js'))->loadedOnRequest(),
            Js::make('dash-app', resource_path('js/dashboards/dash-app.js'))->loadedOnRequest(),
            Js::make('dash-catalog', resource_path('js/dashboards/dash-catalog.js'))->loadedOnRequest(),
            // Maliyet kalemleri denemesi (D-187): yalniz UI Deneme > Maliyet kalemleri.
            Js::make('cost-lab', resource_path('js/dashboards/cost-lab.js'))->loadedOnRequest(),
            // Yeni pano (D-207, kullanici 14 Ekim 2026: "react ile yap"): yalniz UI Deneme > Yeni pano.
            Css::make('konelsis-nd', resource_path('css/filament/konelsis-nd.css'))->loadedOnRequest(),
            Js::make('nd-app', resource_path('js/dashboards/nd-app.js'))->loadedOnRequest(),
            // Masaustu (Windows) bildirimi + bildirim sesi (D-126, kullanici onayi
            // 25 Eylul 2026): her panel sayfasinda; ayarlari AlertFeed verir.
            Js::make('konelsis-alerts', resource_path('js/konelsis-alerts.js')),
            // Raporu bicimli kopyala (D-167, kullanici onayi 6 Ekim 2026): kucuk
            // yardimci; rapor listesi, rapor detayi ve ileride rapor sekmeleri kullanir.
            Js::make('report-copy', resource_path('js/report-copy.js')),
            // Baglantiya gitmeden once onay penceresi (D-200, kullanici onayi 13 Ekim
            // 2026: "JavaScript, tek nokta"); her panel sayfasinda, ayarlar asagida.
            Js::make('konelsis-link-guard', resource_path('js/konelsis-link-guard.js')),
        ], 'konelsis');

        // Baglanti onayi metinleri ve hedef sayfa adlari (adres oneki => kayit turu,
        // pencere basligi icin); Feature::LinkGuard kapaliysa veri yok, betik calismaz.
        Filament::serving(function (): void {
            if (! auth()->user() instanceof Personnel || ! FeatureFlags::enabled(Feature::LinkGuard)) {
                return;
            }

            FilamentAsset::registerScriptData([
                'konelsisLinkGuard' => [
                    'labels' => [
                        'title' => (string) __('link_guard.title'),
                        'title_type' => (string) __('link_guard.title_type'),
                        'body' => (string) __('link_guard.body'),
                        'body_no_field' => (string) __('link_guard.body_no_field'),
                        'body_no_target' => (string) __('link_guard.body_no_target'),
                        'stay' => (string) __('link_guard.stay'),
                        'row' => (string) __('link_guard.row'),
                        'go' => (string) __('link_guard.go'),
                        'go_type' => (string) __('link_guard.go_type'),
                        'close' => (string) __('link_guard.close'),
                    ],
                    'targets' => $this->linkGuardTargets(),
                ],
            ]);
        });

        // Betik ayarlari istek aninda (oturum, dil ve adres hazir): window.filamentData.konelsisAlerts.
        Filament::serving(function (): void {
            $user = auth()->user();

            if (! $user instanceof Personnel || ! $user->isActive() || ! FeatureFlags::enabled(Feature::DesktopAlerts)
                || (! FeatureFlags::enabled(Feature::AlertSound) && ! FeatureFlags::enabled(Feature::AlertWindows))) {
                return;
            }

            FilamentAsset::registerScriptData([
                'konelsisAlerts' => [
                    'feed' => route('filament.admin.notifications.feed'),
                    'user' => (int) $user->getKey(),
                    'poll' => 20000,
                    // Ses ve Windows bildirimi ayri ozelliklerdir (D-128).
                    'sound' => FeatureFlags::enabled(Feature::AlertSound),
                    'windows' => FeatureFlags::enabled(Feature::AlertWindows),
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

    /**
     * Baglanti onayi penceresinin basligi icin panel kaynaklarinin adres
     * onekleri ve kayit turu adlari ("/admin/business-cases" => "Potansiyel
     * iş"); ad detay sayfasi basligiyla aynidir (RecordHeading::typeLabel,
     * record_heading.labels).
     *
     * @return array<string, string>
     */
    private function linkGuardTargets(): array
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null) {
            return [];
        }

        $base = '/'.trim($panel->getPath(), '/');
        $targets = [];

        foreach ($panel->getResources() as $resource) {
            try {
                // Detay sayfasi basligiyla ayni tur adi (RecordHeading, D-205).
                $targets[$base.'/'.$resource::getSlug($panel)] = RecordHeading::typeLabel($resource);
            } catch (Throwable) {
                continue;
            }
        }

        return $targets;
    }
}
