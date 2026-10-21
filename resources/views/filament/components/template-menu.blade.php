{{--
    Ust cubuk Sablonlar menusu (D-226; satir dugmeleri kullanici onayi 19 Ekim
    2026: "sablonlarin yaninda goruntule - indir butonu olmali direk indirme
    olmamali"). Filament'in acilir menu satiri tek tiklama hedefi olabildigi icin
    satirlar bu gorunumde: ad, uzanti, Goruntule (yeni sekme) ve Indir simge
    dugmeleri. Acma / kapama Filament'in kendi acilir menusu (x-filament::dropdown);
    ozel JavaScript yok. Icerik App\Filament\Support\TemplateMenu, yerlesim
    resources/css/filament/konelsis.css `.kc-tpl*`.

    @var array<string, mixed> $menu
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $label = __('template_menu.button');
    $groups = [
        'department' => $menu['units'] !== []
            ? __('template_menu.department_of', ['units' => implode(', ', $menu['units'])])
            : __('template_menu.department'),
        'common' => __('template_menu.common'),
    ];
@endphp

<x-filament::dropdown placement="bottom-end" width="sm" max-height="28rem" :shift="true" class="kc-tpl-menu">
    <x-slot name="trigger">
        <x-filament::button
            color="gray"
            size="xs"
            :icon="Heroicon::OutlinedFolderOpen"
            labeled-from="xl"
            :tooltip="$label"
        >
            {{ $label }}
        </x-filament::button>
    </x-slot>

    @if (filled($menu['createUrl']))
        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item tag="a" :href="$menu['createUrl']" :icon="Heroicon::OutlinedPlus" color="success">
                {{ __('template_menu.add') }}
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    @endif

    @foreach ($groups as $key => $heading)
        @if ($menu[$key] !== [])
            <x-filament::dropdown.list>
                <div class="kc-tpl-head">{{ $heading }}</div>

                @foreach ($menu[$key] as $template)
                    <div class="kc-tpl-row">
                        <x-filament::icon :icon="Heroicon::OutlinedDocumentText" class="kc-tpl-icon" />
                        <span class="kc-tpl-name" title="{{ $template['title'] }}">{{ $template['title'] }}</span>

                        @if (filled($template['ext']))
                            <span class="kc-tpl-ext">{{ $template['ext'] }}</span>
                        @endif

                        @if (filled($template['view']))
                            <x-filament::icon-button
                                tag="a"
                                :href="$template['view']"
                                target="_blank"
                                rel="noopener"
                                color="gray"
                                size="sm"
                                :icon="Heroicon::OutlinedEye"
                                :label="__('template_menu.view')"
                                :tooltip="__('template_menu.view')"
                            />
                        @endif

                        <x-filament::icon-button
                            tag="a"
                            :href="$template['download']"
                            download
                            color="gray"
                            size="sm"
                            :icon="Heroicon::OutlinedArrowDownTray"
                            :label="__('template_menu.download')"
                            :tooltip="__('template_menu.download')"
                        />
                    </div>
                @endforeach
            </x-filament::dropdown.list>
        @endif
    @endforeach

    @if ($menu['department'] === [] && $menu['common'] === [])
        <x-filament::dropdown.list>
            <div class="kc-tpl-empty">{{ __('template_menu.empty') }}</div>
        </x-filament::dropdown.list>
    @endif

    @if (filled($menu['allUrl']))
        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item tag="a" :href="$menu['allUrl']" :icon="Heroicon::OutlinedFolderOpen">
                {{ __('template_menu.all') }}
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    @endif
</x-filament::dropdown>
