{{--
    Is panosu betikleri (B36, D-115). Yalniz ilgili sayfanin BODY_END
    kancasidir (AdminPanelProvider): Is panosu, Kontrol matrisi, Analiz panosu
    ve personel karti (Dikkat karti).

    - React'i sohbet baslaticisi ya da Akilli is ekle penceresi getiriyorsa
      yeniden eklenmez (iki kopya hook'lari bozar; ReactRuntime::loaded()).
    - Akilli is ekle acikken (D-235) work-core ve work-quick her sayfada
      filament.work.quick-global'den once gelir; burada tekrar eklenmez.
    - Sira sabittir: work-core -> ekran betigi. Hepsi `defer`.

    $screens: ['work-board'] | ['work-matrix'] | ['work-analysis'] | ['work-attention']
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use App\Filament\Support\WorkAppConfig;
    use Filament\Support\Facades\FilamentAsset;

    $workScripts = ['work-core', ...($screens ?? [])];

    if (WorkAppConfig::smartOn()) {
        $workScripts = array_values(array_diff($workScripts, ['work-core', 'work-quick']));
    }
@endphp

@unless (ReactRuntime::loaded())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

@foreach ($workScripts as $workScript)
    <script src="{{ FilamentAsset::getScriptSrc($workScript, package: 'konelsis') }}" defer></script>
@endforeach
