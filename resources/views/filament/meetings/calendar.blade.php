{{--
    Gorusme plani takvimi (B34, D-109). Sosyal medya sayfasiyla ayni yapi:
    yalniz stil baglantisi ve React kok ogesi; betikler sayfaya ozel BODY_END
    kancasindan gelir (filament.meetings.scripts). Takvim izgarasi ve stilleri
    sosyal medya takviminin ortak parcasidir (social-calendar.js,
    konelsis-social.css PLANNER bolumu); burada kopyasi yoktur.

    $config: App\Filament\Support\MeetingPlanAppConfig::make().
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
            data-ks-root="meetings"
            data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        ></div>
    </div>
</x-filament-panels::page>
