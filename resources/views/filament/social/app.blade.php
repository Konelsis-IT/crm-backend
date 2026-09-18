{{--
    Sosyal Medya sayfasi (B31, D-106; 18 Eylul 2026 kullanici karari: "sayfa
    Filament resource olacak ama icerigi tamamen React tasarimi olacak").

    Bu gorunum YALNIZ stil baglantisini ve React kok ogesini icerir; betik
    etiketi YOKTUR. Betikler sayfaya ozel BODY_END kancasindan gelir
    (resources/views/filament/social/scripts.blade.php), cunku sohbet
    baslaticisi React'i BODY_END'de yukler ve `defer` betikler belge sirasina
    gore calisir: burada olsalardi React'ten once calisirlardi.

    - `x-filament-panels::page` sarmalayicisi korunur (eylem pencereleri,
      kancalar, hata bildirimi betigi). Baslik / iz bos oldugundan baslik blogu
      cizilmez (ManageSocialMedia).
    - Sayfa bir Livewire bilesenidir; `wire:ignore` olmadan her yeniden cizimde
      React agaci silinirdi.
    - $config: App\Filament\Support\SocialAppConfig::make().
--}}
@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<x-filament-panels::page>
    <link
        rel="stylesheet"
        href="{{ FilamentAsset::getStyleHref('konelsis-social', package: 'konelsis') }}"
    />

    <div wire:ignore>
        <div
            id="konelsis-social-root"
            data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        ></div>
    </div>
</x-filament-panels::page>
