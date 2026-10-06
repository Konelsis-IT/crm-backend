{{--
    Teklif oncesi kontrol listesi tahtasi (D-157). Yalniz potansiyel is olustur /
    duzenle sayfalarinin BODY_END kancasidir (AdminPanelProvider): tahtanin ve
    Kaydet / Ileri ozetinin stili ile React tahtasi.

    - React'i sohbet baslaticisi getiriyorsa yeniden eklenmez (iki kopya hook'lari bozar).
    - Sira sabittir: react -> react-dom -> checklist-board. Hepsi `defer`; tahta
      hazir olunca `konelsis-checklist:ready` olayini yayar, alan onu bekler.
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use Filament\Support\Facades\FilamentAsset;
@endphp

<link rel="stylesheet" href="{{ FilamentAsset::getStyleHref('konelsis-checklist', package: 'konelsis') }}" />

@unless (ReactRuntime::providedByChat())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

<script src="{{ FilamentAsset::getScriptSrc('checklist-board', package: 'konelsis') }}" defer></script>
