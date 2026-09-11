{{--
    Kurum ici sohbet baslaticisi (D-83, 11 Eylul 2026 kullanici karari:
    "sag alttan erisilen genel bir sohbet butonu; React ile gelistirilmeli").
    Panel govdesinin sonuna (PanelsRenderHook::BODY_END) yalniz oturum acmis
    personel icin ve B12A uygulandiysa eklenir. React ve uygulama betikleri
    Filament varlik sistemiyle kayitlidir (FilamentAssetsProvider, paket
    "konelsis"); `php artisan filament:assets` ile public/js/konelsis/ altina
    yayimlanir. Arayuz metinleri lang/chat.php `ui` dizisinden gelir.
--}}
@php
    use App\Filament\Resources\WorkRequests\Pages\CreateWorkRequest;
    use App\Filament\Resources\WorkRequests\WorkRequestResource;
    use Filament\Support\Facades\FilamentAsset;

    $config = [
        'base' => route('filament.admin.chat.bootstrap', absolute: true),
        'endpoints' => [
            'bootstrap' => route('filament.admin.chat.bootstrap'),
            'sync' => route('filament.admin.chat.sync'),
            'conversations' => route('filament.admin.chat.conversations'),
            'directory' => route('filament.admin.chat.directory'),
            'documents' => route('filament.admin.chat.documents'),
            'conversation' => route('filament.admin.chat.messages', ['conversation' => '__ID__']),
            'message' => route('filament.admin.chat.messages.delete', ['message' => '__ID__']),
            // Mesajdan talep acma (D-84): kaynak yoksa dugme gorunmez.
            'request_create' => WorkRequestResource::canAccess()
                ? WorkRequestResource::getUrl('create').'?'.CreateWorkRequest::QUERY_SOURCE_MESSAGE.'=__ID__'
                : null,
        ],
        'csrf' => csrf_token(),
        'locale' => app()->getLocale(),
        'labels' => __('chat.ui'),
        'panelUrl' => url('/admin'),
        'logo' => asset('images/konelsis-favicon.png'),
    ];
@endphp

<div
    id="konelsis-chat-root"
    data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
></div>

<script src="{{ FilamentAsset::getScriptSrc('react', package: 'konelsis') }}" defer></script>
<script src="{{ FilamentAsset::getScriptSrc('react-dom', package: 'konelsis') }}" defer></script>
<script src="{{ FilamentAsset::getScriptSrc('konelsis-chat', package: 'konelsis') }}" defer></script>
