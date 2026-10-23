{{--
    Is panosu ailesinin sayfa ici React koku (D-240): bir Filament sayfasinin
    icerigine (tablo yerine) yerlesir; stil ve betikler kancalardan gelir
    (filament.work.quick-global, filament.work.scripts). Livewire yeniden
    cizdiginde React agacina dokunmaz (wire:ignore).

    $root: work-list
    $config: App\Filament\Support\WorkAppConfig::workList().
--}}
<div wire:ignore>
    <div
        data-kw-root="{{ $root }}"
        data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    ></div>
</div>
