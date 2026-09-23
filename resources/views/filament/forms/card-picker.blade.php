{{--
    Kart secici (App\Filament\Forms\Components\CardPicker): ikon + baslik +
    aciklama kartlari. Durum Filament alanindadir; secim Livewire'a aninda
    yazilir (alan `live()` ise taslagin alanlari hemen asagida acilir).
    Stiller: resources/css/filament/konelsis.css `.kc-picker*`.
--}}
@php
    $cards = $getCards();
    $disabled = $isDisabled();
@endphp

<div
    x-data="{ state: $wire.$entangle('{{ $getStatePath() }}') }"
    class="kc-picker"
    role="radiogroup"
    @if (filled($getLabel())) aria-label="{{ $getLabel() }}" @endif
>
    @foreach ($cards as $card)
        <button
            type="button"
            role="radio"
            class="kc-picker-card"
            @disabled($disabled)
            x-bind:aria-checked="state === @js($card['value']) ? 'true' : 'false'"
            x-bind:class="state === @js($card['value']) && 'is-selected'"
            @unless ($disabled)
                {{-- Yerel durum aninda isaretlenir; sunucuya da yazilir ki
                     taslagin alanlari hemen asagida acilsin. --}}
                x-on:click="state = @js($card['value']); $wire.set(@js($getStatePath()), @js($card['value']))"
            @endunless
        >
            <span class="kc-picker-icon" aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($card['icon']) }}
            </span>
            <span class="kc-picker-title">{{ $card['label'] }}</span>

            @if (filled($card['description']))
                <span class="kc-picker-text">{{ $card['description'] }}</span>
            @endif
        </button>
    @endforeach
</div>
