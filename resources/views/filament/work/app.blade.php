{{--
    Is panosu React ekranlari (B36, D-115; 22 Eylul 2026 kullanici onayi:
    pano, kontrol matrisi ve analiz panosu React ile). Gorunum yalniz stil
    baglantisini ve React kok ogesini icerir; betikler sayfaya ozel BODY_END
    kancasindan gelir (filament.work.scripts). Basligi ve kirinti yolunu
    React cizer.

    $root: work-board | work-matrix | work-analysis
    $config: App\Filament\Support\WorkAppConfig::board() / matrix() / analysis().
--}}
@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<x-filament-panels::page>
    <link
        rel="stylesheet"
        href="{{ FilamentAsset::getStyleHref('konelsis-work', package: 'konelsis') }}"
    />

    <div wire:ignore>
        <div
            data-kw-root="{{ $root }}"
            data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        ></div>
    </div>
</x-filament-panels::page>
