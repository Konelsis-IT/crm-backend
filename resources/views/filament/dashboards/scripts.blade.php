{{--
    Departman panolari betikleri (D-173). Yalniz ilgili UI Deneme sayfalarinin
    BODY_END kancasidir (AdminPanelProvider); filament/work/scripts.blade.php
    ile ayni kalip.

    - React'i sohbet baslaticisi getiriyorsa yeniden eklenmez (iki kopya hook'lari bozar).
    - Sira sabittir: dash-core -> dash-widgets -> ekran betigi. Hepsi `defer`.

    $screens: ['dash-app'] | ['dash-catalog']
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use Filament\Support\Facades\FilamentAsset;
@endphp

@unless (ReactRuntime::providedByChat())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

@foreach (['dash-core', 'dash-widgets', ...($screens ?? [])] as $dashScript)
    <script src="{{ FilamentAsset::getScriptSrc($dashScript, package: 'konelsis') }}" defer></script>
@endforeach
