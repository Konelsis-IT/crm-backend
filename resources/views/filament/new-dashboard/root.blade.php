{{--
    Departman panosunun React koku (D-207 / D-212): stil baglantisi ve kok oge.
    UI Deneme > Yeni pano (app.blade.php) ve Genel bakis (Dashboard::content,
    kisinin departman panosu varsa) ayni parcayi kullanir; betikler sayfaya
    ozel BODY_END kancasindan gelir (filament.new-dashboard.scripts).

    $config: App\Filament\Support\NewDashboardConfig::make().
--}}
@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<link
    rel="stylesheet"
    href="{{ FilamentAsset::getStyleHref('konelsis-nd', package: 'konelsis') }}"
/>

<div wire:ignore>
    <div
        data-nd-root
        data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    ></div>
</div>
