{{--
    Teklif oncesi kontrol listesi tahtasi (App\Filament\Forms\Components\ChecklistBoard,
    D-157; kullanici onayi 5 Ekim 2026: "istedigim tasarim Filament ile olmadiginda
    React ile yapabilirsin").

    - Dis kutu Livewire ile her cizimde yenilenir; data-config formun o anki
      durumudur (secili listeler, agirliklar, cevaplar, belgeler). Tahta bu
      ozelligi izler (MutationObserver).
    - Ic kok `wire:ignore`: React'in cizdigi agaca Livewire dokunmaz.
    - Betik ve stil sayfaya ozel BODY_END kancasindan gelir
      (resources/views/filament/acquisition/checklist-scripts.blade.php).
--}}
<div
    class="kc-cl-host"
    data-config="{{ json_encode($getBoardConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    x-data="{
        board: null,
        init() {
            const start = () => { this.board = window.KonelsisChecklist.mount(this.$refs.root, this.$el, this.$wire) }
            window.KonelsisChecklist ? start() : window.addEventListener('konelsis-checklist:ready', start, { once: true })
        },
        destroy() {
            if (this.board) { this.board.unmount() }
        },
    }"
>
    <div wire:ignore x-ref="root" class="kc-cl-root"></div>
</div>
