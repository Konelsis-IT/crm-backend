{{--
    Departman panolari React ekranlari (D-173; 8 Ekim 2026 kullanici onayi:
    panolar "tamamen React ile"). filament/work/app.blade.php ile ayni kalip:
    gorunum yalniz stil baglantisini ve React kok ogesini icerir; betikler
    sayfaya ozel BODY_END kancasindan gelir (filament.dashboards.scripts).
    Basligi ve departman seciciyi React cizer.

    $root: dash-app | dash-catalog
    $config: App\Filament\Support\DashboardAppConfig::make().
--}}
@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<x-filament-panels::page>
    <link
        rel="stylesheet"
        href="{{ FilamentAsset::getStyleHref('konelsis-dash', package: 'konelsis') }}"
    />

    <div wire:ignore>
        <div
            data-kd-root="{{ $root }}"
            data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        ></div>
    </div>
</x-filament-panels::page>
