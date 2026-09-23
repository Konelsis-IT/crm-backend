{{--
    Is panosu betikleri (B36, D-115). Yalniz ilgili sayfanin BODY_END
    kancasidir (AdminPanelProvider): Is panosu, Kontrol matrisi, Analiz panosu
    ve personel karti (Dikkat karti).

    - React'i sohbet baslaticisi getiriyorsa yeniden eklenmez (iki kopya hook'lari bozar).
    - Sira sabittir: work-core -> ekran betigi. Hepsi `defer`.

    $screens: ['work-board'] | ['work-matrix'] | ['work-analysis'] | ['work-attention']
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use Filament\Support\Facades\FilamentAsset;
@endphp

@unless (ReactRuntime::providedByChat())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

@foreach (['work-core', ...($screens ?? [])] as $workScript)
    <script src="{{ FilamentAsset::getScriptSrc($workScript, package: 'konelsis') }}" defer></script>
@endforeach
