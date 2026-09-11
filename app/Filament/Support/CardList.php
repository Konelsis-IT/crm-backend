<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Contracts\HasCardView;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Kart listesi (D-79): kapakli kayit kartini saran arac cubugu (arama, sayfa
 * boyu), kart izgarasi ve sayfalama. Durum sayfanin InteractsWithCardView
 * ozelliklerinde yasar; arama ve sayfa boyu alanlari o ozelliklere baglanir,
 * sayfa dugmeleri HasCardView::goToCardPage'i cagirir.
 */
final class CardList
{
    public const DEFAULT_PER_PAGE = 12;

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [12, 24, 48];

    /** Aktif sayfanin iki yaninda gosterilecek sayfa dugmesi sayisi. */
    private const WINDOW = 2;

    public function __construct(private readonly CardGallery $gallery) {}

    /**
     * @param  Closure(\Illuminate\Database\Eloquent\Model): Component  $card
     * @return list<Component>
     */
    public function components(
        LengthAwarePaginator $paginator,
        Closure $card,
        ?string $searchPlaceholder = null,
        string $variant = CardGallery::VARIANT_COVER,
    ): array {
        $cards = array_map($card, $paginator->items());

        return [
            $this->toolbar($searchPlaceholder),
            $cards === []
                ? Callout::make(__('card_view.empty'))->color('gray')->icon(Heroicon::OutlinedMagnifyingGlass)
                : $this->gallery->grid(array_values($cards), $variant),
            $this->pagination($paginator),
        ];
    }

    private function toolbar(?string $placeholder): Component
    {
        $perPageOptions = [];

        foreach (self::PER_PAGE_OPTIONS as $count) {
            $perPageOptions[$count] = __('card_view.per_page', ['count' => $count]);
        }

        return Flex::make([
            TextInput::make('cardSearch')
                ->hiddenLabel()
                ->placeholder($placeholder ?? __('card_view.search'))
                ->prefixIcon(Heroicon::OutlinedMagnifyingGlass)
                ->autocomplete(false)
                ->live(debounce: 500),
            Select::make('cardPerPage')
                ->hiddenLabel()
                ->options($perPageOptions)
                ->selectablePlaceholder(false)
                ->live()
                ->grow(false),
        ])
            ->verticalAlignment(VerticalAlignment::Center)
            ->extraAttributes(['class' => 'konelsis-card-toolbar']);
    }

    private function pagination(LengthAwarePaginator $paginator): Component
    {
        $current = $paginator->currentPage();
        $last = max(1, $paginator->lastPage());

        $summary = $paginator->total() === 0
            ? __('card_view.summary_empty')
            : __('card_view.summary', [
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]);

        $actions = [
            Action::make('card_page_prev')
                ->iconButton()
                ->icon(Heroicon::OutlinedChevronLeft)
                ->label(__('card_view.prev'))
                ->tooltip(__('card_view.prev'))
                ->color('gray')
                ->size(Size::Small)
                ->disabled($current <= 1)
                ->action(fn (HasCardView $livewire) => $livewire->goToCardPage($current - 1)),
        ];

        foreach ($this->pageWindow($current, $last) as $index => $page) {
            $actions[] = $page === null
                ? Action::make('card_page_gap_'.$index)
                    ->label('…')
                    ->link()
                    ->color('gray')
                    ->size(Size::Small)
                    ->disabled()
                : Action::make('card_page_'.$page)
                    ->label((string) $page)
                    ->tooltip(__('card_view.page', ['page' => $page]))
                    ->color($page === $current ? 'primary' : 'gray')
                    ->size(Size::Small)
                    ->action(fn (HasCardView $livewire) => $livewire->goToCardPage($page));
        }

        $actions[] = Action::make('card_page_next')
            ->iconButton()
            ->icon(Heroicon::OutlinedChevronRight)
            ->label(__('card_view.next'))
            ->tooltip(__('card_view.next'))
            ->color('gray')
            ->size(Size::Small)
            ->disabled($current >= $last)
            ->action(fn (HasCardView $livewire) => $livewire->goToCardPage($current + 1));

        return Flex::make([
            Text::make($summary)->color('gray'),
            // alignEnd() Filament'te .fi-align-end -> flex-row-reverse uygular
            // (vendor/filament/actions/resources/css/actions.css); bu da sayfa
            // dugmelerini "sonraki, son, ..., 2, 1, onceki" diye TERSINE
            // ciziyordu (11 Eylul 2026, kullanici bildirimi). Sag hiza zaten
            // sarmalayan Flex'ten (grow(false)) geliyor; alignEnd gereksizdi.
            Actions::make($actions)->grow(false),
        ])
            ->verticalAlignment(VerticalAlignment::Center)
            ->extraAttributes(['class' => 'konelsis-card-pagination']);
    }

    /**
     * Sayfa dugmeleri: az sayfada hepsi; cok sayfada ilk, son ve aktif
     * sayfanin cevresi, aradaki bosluklar null (…) ile.
     *
     * @return list<int | null>
     */
    private function pageWindow(int $current, int $last): array
    {
        if ($last <= (2 * self::WINDOW) + 3) {
            return range(1, $last);
        }

        $pages = [1];
        $start = max(2, $current - self::WINDOW);
        $end = min($last - 1, $current + self::WINDOW);

        if ($start > 2) {
            $pages[] = null;
        }

        for ($page = $start; $page <= $end; $page++) {
            $pages[] = $page;
        }

        if ($end < $last - 1) {
            $pages[] = null;
        }

        $pages[] = $last;

        return $pages;
    }
}
