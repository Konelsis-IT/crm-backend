{{--
    Yeni pano betikleri (D-207, React). Yalniz UI Deneme > Yeni pano sayfasinin
    BODY_END kancasidir (AdminPanelProvider). React'i sohbet baslaticisi
    getiriyorsa yeniden eklenmez (iki kopya hook'lari bozar). Hepsi `defer`.
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use Filament\Support\Facades\FilamentAsset;
@endphp

@unless (ReactRuntime::loaded())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

<script src="{{ FilamentAsset::getScriptSrc('nd-app', package: 'konelsis') }}" defer></script>
