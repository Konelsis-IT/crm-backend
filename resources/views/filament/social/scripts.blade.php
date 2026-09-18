{{--
    Sosyal Medya React betikleri (B31, D-106). AdminPanelProvider'da sohbet
    kancasindan SONRA, yalniz ManageSocialMedia sayfasina ozel
    PanelsRenderHook::BODY_END kancasi olarak kayitlidir. Kapsamsiz kancalar
    kapsamli olanlardan once cizildigi icin belge sirasi her zaman:
    react, react-dom, konelsis-chat, social-*.

    - React'i sohbet baslaticisi getiriyorsa (ReactRuntime::providedByChat())
      yeniden EKLENMEZ; iki React kopyasi hook'lari bozar.
    - Dokuz dosyanin sirasi sabittir: core her seyi tanimlar, app en sonda
      baglar. Hepsi `defer`; social-app DOM hazirsa hemen, degilse
      DOMContentLoaded'da baglanir.
    - Varliklar FilamentAssetsProvider'da "konelsis" paketiyle kayitlidir;
      `php artisan filament:assets` ile public/js/konelsis/ altina yayimlanir.
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use Filament\Support\Facades\FilamentAsset;

    $socialScripts = [
        'social-core',
        'social-editor',
        'social-feed',
        'social-detail',
        'social-composer',
        'social-planner',
        'social-insights',
        'social-manage',
        'social-app',
    ];
@endphp

@unless (ReactRuntime::providedByChat())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

@foreach ($socialScripts as $socialScript)
    <script src="{{ FilamentAsset::getScriptSrc($socialScript, package: 'konelsis') }}" defer></script>
@endforeach
