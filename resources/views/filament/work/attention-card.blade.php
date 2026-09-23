{{--
    Personel karti > Dikkat karti (B36, D-115; React bileseni). Ozel yetkiyle
    (ViewAttentionCard:WorkItem) gorunur; kisi kendi kartini gormez (gorunurluk
    PersonnelInfolist'te). Betik ViewPersonnelRecord kapsamli BODY_END
    kancasindan gelir (filament.work.scripts, work-attention).

    $config: App\Filament\Support\WorkAppConfig::attention($personnel).
--}}
@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<div wire:ignore>
    <link
        rel="stylesheet"
        href="{{ FilamentAsset::getStyleHref('konelsis-work', package: 'konelsis') }}"
    />

    <div
        data-kw-root="work-attention"
        data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    ></div>
</div>
