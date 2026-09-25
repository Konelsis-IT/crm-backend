{{--
    Hizli islemler (D-122): ust cubuktaki dugme ve tiklandigi yerde acilan
    daire (Dynamics 365 tarzi; kullanici onayi 24 Eylul 2026, Blade + CSS).
    Acma / kapama ve dugmeye bagli konum Filament'in kendi acilir menusudur
    (x-filament::dropdown); ozel JavaScript yoktur. Islemler ve yetki
    suzmesi App\Filament\Support\QuickActions\QuickActionCatalog'dadir.
    Daire yerlesimi: resources/css/filament/konelsis.css `.kc-qa*`.

    @var list<\App\Filament\Support\QuickActions\QuickAction> $actions
    @var string $manageUrl
    @var string $label
    @var string $addLabel
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $count = count($actions) + 1;
@endphp

<x-filament::dropdown placement="bottom-end" :offset="10" width="sm" class="kc-qa-dropdown">
    <x-slot name="trigger">
        <x-filament::button
            color="gray"
            size="xs"
            :icon="Heroicon::OutlinedBolt"
            labeled-from="lg"
            :tooltip="$label"
        >
            {{ $label }}
        </x-filament::button>
    </x-slot>

    <nav class="kc-qa" aria-label="{{ $label }}" style="--kc-qa-n: {{ $count }}">
        <div class="kc-qa-center" aria-hidden="true">
            <x-filament::icon :icon="Heroicon::OutlinedBolt" class="kc-qa-center-icon" />
            <span class="kc-qa-center-title">{{ $label }}</span>
        </div>

        @foreach ($actions as $action)
            <a
                href="{{ $action->url }}"
                class="kc-qa-node"
                style="--kc-qa-i: {{ $loop->index }}"
            >
                <span class="kc-qa-bubble">
                    <x-filament::icon :icon="$action->icon" class="kc-qa-icon" />
                </span>
                <span class="kc-qa-label">{{ $action->label }}</span>
            </a>
        @endforeach

        <a
            href="{{ $manageUrl }}"
            class="kc-qa-node kc-qa-node-add"
            style="--kc-qa-i: {{ $count - 1 }}"
        >
            <span class="kc-qa-bubble">
                <x-filament::icon :icon="Heroicon::OutlinedPlus" class="kc-qa-icon" />
            </span>
            <span class="kc-qa-label">{{ $addLabel }}</span>
        </a>
    </nav>
</x-filament::dropdown>
