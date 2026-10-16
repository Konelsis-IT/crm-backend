{{--
    Yeni pano (D-207, React; kullanici 14 Ekim 2026: "react ile yap").
    UI Deneme > Yeni pano; koku filament.new-dashboard.root cizer (Genel bakis
    da ayni parcayi kullanir, D-212). Basligi, sekmeleri, acil seridi ve acil
    uyari penceresini React cizer.

    $config: App\Filament\Support\NewDashboardConfig::make().
--}}
<x-filament-panels::page>
    @include('filament.new-dashboard.root', ['config' => $config])
</x-filament-panels::page>
