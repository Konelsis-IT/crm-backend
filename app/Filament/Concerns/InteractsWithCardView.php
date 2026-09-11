<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\CardList;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;

/**
 * Liste sayfasina kart gorunumu ekler (D-79): kapakli kayit karti + arama +
 * sayfalama. Varsayilan gorunum karttir; Liste/Kart secimi oturumda sayfa
 * bazinda saklanir. Arama, sayfa ve sayfa boyu adres cubugunda tasinir
 * (?q=&sayfa=&adet=) ki baglanti paylasilabilsin.
 *
 * Kullanan sayfa iki seyi soyler: kayitlari nasil getirecegi (cardPaginator)
 * ve bir kaydi hangi karta cevirecegi (cardFor).
 */
trait InteractsWithCardView
{
    /** Kart mi liste mi? Varsayilan kart. */
    #[Session]
    public bool $cardView = true;

    #[Url(as: 'q', except: '')]
    public ?string $cardSearch = '';

    #[Url(as: 'sayfa', except: 1)]
    public int | string $cardPage = 1;

    #[Url(as: 'adet', except: CardList::DEFAULT_PER_PAGE)]
    public int | string $cardPerPage = CardList::DEFAULT_PER_PAGE;

    /** Aktif arama/sayfa/sayfa boyu ile sayfalanmis kayitlar. */
    abstract protected function cardPaginator(): LengthAwarePaginator;

    /** Bir kaydin kapakli karti. */
    abstract protected function cardFor(Model $record): Component;

    public function updatedCardSearch(): void
    {
        $this->cardPage = 1;
    }

    public function updatedCardPerPage(): void
    {
        $this->cardPage = 1;
    }

    public function goToCardPage(int $page): void
    {
        $this->cardPage = max(1, $page);

        $this->forgetContentSchema();
    }

    public function toggleCardView(): void
    {
        $this->cardView = ! $this->cardView;

        $this->forgetContentSchema();
    }

    public function cardSearchTerm(): ?string
    {
        $term = trim((string) $this->cardSearch);

        return $term === '' ? null : $term;
    }

    public function cardPerPageValue(): int
    {
        $value = (int) $this->cardPerPage;

        return in_array($value, CardList::PER_PAGE_OPTIONS, true) ? $value : CardList::DEFAULT_PER_PAGE;
    }

    public function cardPageValue(): int
    {
        return max(1, (int) $this->cardPage);
    }

    /**
     * Liste sayfasi icerigi: kart modunda arac cubugu + izgara + sayfalama,
     * liste modunda ust sinifin tablosu.
     */
    public function content(Schema $schema): Schema
    {
        if (! $this->cardView) {
            return parent::content($schema);
        }

        return $schema->columns(1)->components($this->cardListComponents());
    }

    /**
     * Kart gorunumunun bilesenleri. Arama sonucu sayfa sayisini dusurduyse
     * istenen sayfa son sayfaya cekilir.
     *
     * @return list<Component>
     */
    protected function cardListComponents(?string $searchPlaceholder = null): array
    {
        $paginator = $this->cardPaginator();

        if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            $this->cardPage = $paginator->lastPage();
            $paginator = $this->cardPaginator();
        }

        return app(CardList::class)->components(
            $paginator,
            fn (Model $record): Component => $this->cardFor($record),
            $searchPlaceholder,
        );
    }

    /** Baslik eylemi: yalniz simge; Liste <-> Kart. */
    protected function cardViewToggleAction(): Action
    {
        return Action::make('card_view')
            ->iconButton()
            ->label(fn (): string => $this->cardView ? __('card_view.show_list') : __('card_view.show_cards'))
            ->tooltip(fn (): string => $this->cardView ? __('card_view.show_list') : __('card_view.show_cards'))
            ->icon(fn (): Heroicon => $this->cardView ? Heroicon::OutlinedBars3 : Heroicon::OutlinedSquares2x2)
            ->color('gray')
            ->action(fn () => $this->toggleCardView());
    }

    /**
     * Icerik semasi bir istekte bir kez kurulup onbellege alinir; eylem
     * calistiktan sonra durum degistiyse ayni istekte yeniden kurulsun diye
     * onbellek bosaltilir.
     */
    protected function forgetContentSchema(): void
    {
        unset($this->cachedSchemas['content']);
    }
}
