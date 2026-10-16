{{-- Konelsis AI okuma ilerlemesi (D-192): okuma suruyorken 5 sn'de bir sonucu yoklar. --}}
<div @if ($reading) wire:poll.5s="check" @endif>
    @if ($reading)
        <x-filament::section icon="heroicon-o-sparkles" icon-color="warning" compact>
            <x-slot name="heading">{{ __('purchase_quotation_ai.progress.title') }}</x-slot>

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-filament::loading-indicator style="width: 1.25rem; height: 1.25rem;" />
                <span>{{ __('purchase_quotation_ai.progress.body', ['count' => $documents]) }}</span>
            </div>
        </x-filament::section>
    @endif
</div>
