{{--
    Gorusme plani takvimi betikleri (B34, D-109). Yalniz MeetingPlanCalendar
    sayfasina ozel BODY_END kancasidir (AdminPanelProvider). Sosyal medya
    cekirdegi (KS), ortak takvim parcasi ve gorusme takvimi yuklenir; sosyal
    medya gorunumleri yuklenmez.

    - React'i sohbet baslaticisi getiriyorsa yeniden eklenmez (iki kopya hook'lari bozar).
    - Sira sabittir: core -> calendar -> meeting-calendar. Hepsi `defer`.
--}}
@php
    use App\Filament\Support\ReactRuntime;
    use Filament\Support\Facades\FilamentAsset;
@endphp

@unless (ReactRuntime::providedByChat())
    <script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
    <script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
@endunless

@foreach (['social-core', 'social-calendar', 'meeting-calendar'] as $meetingScript)
    <script src="{{ FilamentAsset::getScriptSrc($meetingScript, package: 'konelsis') }}" defer></script>
@endforeach
