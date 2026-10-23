{{--
    Akilli is ekle, her sayfada (D-235, kullanici onayi 22 Ekim 2026: "ustte Is
    panosu, Sablonlar ortasina ilgili akilli is ekle butonu"; "Duzeltilme modali
    da yine ayni sekilde akilli is ekle modaliyla ayni olacak"). Kapsamsiz
    BODY_END kancasidir (AdminPanelProvider) ve yalniz WorkAppConfig::smartOn()
    iken cizilir.

    - Kok: [data-kw-root="work-quick"]; ust cubuk dugmesi "kq-open", Isler
      listesi satiri "kq-edit" ({ id }) olayini gonderir (work-quick.js).
    - Stil: konelsis-work.css (Isler listesindeki rozetli okunus da bunu kullanir).
    - React'i sohbet baslaticisi getirmiyorsa burada eklenir; sayfaya ozel
      React betikleri ReactRuntime::loaded() ile tekrar eklemez.
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use App\Filament\Support\WorkAppConfig;
    use Filament\Support\Facades\FilamentAsset;
@endphp

<link rel="stylesheet" href="{{ FilamentAsset::getStyleHref('konelsis-work', package: 'konelsis') }}" />

<div
    data-kw-root="work-quick"
    data-config="{{ json_encode(WorkAppConfig::quick(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
></div>

@unless (ReactRuntime::providedByChat())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

<script src="{{ FilamentAsset::getScriptSrc('work-core', package: 'konelsis') }}" defer></script>
<script src="{{ FilamentAsset::getScriptSrc('work-quick', package: 'konelsis') }}" defer></script>
