<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Notification\AnnouncementPriority;
use App\Enums\Platform\Feature;
use App\Filament\Clusters\UiGallery;
use App\Livewire\UiGallery\AnnouncementListDemo;
use App\Models\Personnel\Personnel;
use App\Query\Ui\AnnouncementGalleryQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * UI Deneme > Liste denemeleri (D-139, 29 Eylul 2026 kullanici istegi):
 * panodaki Duyurular tablosu yarim genislikte tarih / onem / gonderen / kitle
 * sutunlarina yer harcayip metni okunmaz birakiyordu. Burada ayni duyurular
 * 10 farkli yerlesimle, panodaki gibi yarim genislikte yan yana durur; 1-5
 * tablo tabanli (AnnouncementListDemo), 6-10 sema bilesenleriyle. Kalici
 * katalogun parcasidir, silinmez; yeni varyant yanina eklenir.
 */
class UiListGallery extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.lists');
    }

    public function getTitle(): string
    {
        return __('ui_gallery.lists.title');
    }

    public function getSubheading(): ?string
    {
        return __('ui_gallery.lists.subheading');
    }

    /**
     * Calisma arayuzu (D-91): yalniz gelistirme ortaminda, tam yetkili rol.
     */
    public static function canAccess(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        $user = auth()->user();

        return FeatureFlags::enabled(Feature::UiGallery) && $user instanceof Personnel && app(RoleResolver::class)->hasFullAccess($user);
    }

    public function content(Schema $schema): Schema
    {
        ['items' => $items, 'sample' => $sample] = app(AnnouncementGalleryQueries::class)->items();

        $variants = [
            1 => ['inbox', $this->table(AnnouncementListDemo::INBOX)],
            2 => ['panel', $this->table(AnnouncementListDemo::PANEL)],
            3 => ['cards', $this->table(AnnouncementListDemo::CARDS)],
            4 => ['grouped', $this->table(AnnouncementListDemo::GROUPED)],
            5 => ['stripe', $this->table(AnnouncementListDemo::STRIPE)],
            6 => ['callout', $this->card($this->bulletin($items))],
            7 => ['accordion', $this->card($this->accordion($items))],
            8 => ['timeline', $this->card($this->timeline($items))],
            9 => ['featured', $this->card($this->featured($items))],
            10 => ['reader', $this->card($this->reader($items))],
        ];

        $cells = [];

        foreach ($variants as $number => [$key, $demo]) {
            $cells[] = Group::make([
                Text::make($number.' · '.__('ui_gallery.lists.variants.'.$key.'.name'))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large),
                Text::make(__('ui_gallery.lists.variants.'.$key.'.description'))
                    ->color('gray'),
                $demo,
            ]);
        }

        return $schema->columns(1)->components([
            Callout::make(__('ui_gallery.lists.intro_heading'))
                ->description(__('ui_gallery.lists.intro'))
                ->info()
                ->icon(Heroicon::OutlinedQueueList)
                ->footer($sample ? __('ui_gallery.lists.sample_note') : __('ui_gallery.lists.real_note', ['count' => count($items)])),
            // Panodaki gibi yarim genislik: genis ekranda iki deneme yan yana.
            Grid::make(['default' => 1, 'lg' => 2])->components($cells),
        ]);
    }

    private function table(string $variant): Component
    {
        return Livewire::make(AnnouncementListDemo::class, ['variant' => $variant])->key('ui-list-'.$variant);
    }

    /** Sema denemelerinin pano karti: ayni baslik ve aciklama. */
    private function card(array $components): Section
    {
        return Section::make(__('announcement.widget.heading'))
            ->description(__('announcement.widget.description'))
            ->schema($components);
    }

    /**
     * 6: her duyuru onem rengiyle bir bilgi kutusu, metnin tamami gorunur.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<Component>
     */
    private function bulletin(array $items): array
    {
        return array_map(fn (array $item): Component => Callout::make($item['title'])
            ->description($item['body'])
            ->icon($item['priority']->getIcon())
            ->color($item['tone'])
            ->footer($item['meta_full']), $items);
    }

    /**
     * 7: baslik + kunye; en yenisi acik, digerleri tiklayinca acilir.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<Component>
     */
    private function accordion(array $items): array
    {
        return array_map(fn (array $item, int $index): Component => Section::make($item['title'])
            ->description($item['meta_full'])
            ->icon($item['priority']->getIcon())
            ->iconColor($item['tone'])
            ->compact()
            ->collapsible()
            ->collapsed($index > 0)
            ->schema([Text::make($item['body'])]), $items, array_keys($items));
    }

    /**
     * 8: solda gun ve saat, sagda baslik, metin ve kunye; onem rozeti yalniz
     * onemli ve acil duyuruda.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<Component>
     */
    private function timeline(array $items): array
    {
        return array_map(fn (array $item): Component => Grid::make(['default' => 12])
            ->extraAttributes(['class' => 'kc-list-row'])
            ->components([
                Group::make([
                    Text::make($item['day_short'])->weight(FontWeight::Bold),
                    Text::make($item['time'])->size(TextSize::ExtraSmall)->color('gray'),
                ])->columnSpan(['default' => 3]),
                Group::make([
                    Flex::make([
                        Text::make($item['title'])->weight(FontWeight::SemiBold),
                        ...($item['priority'] !== AnnouncementPriority::Normal ? [
                            Text::make($item['priority_label'])->badge()->color($item['tone'])->grow(false),
                        ] : []),
                    ]),
                    Text::make($item['body'])->color('gray'),
                    Text::make($item['meta'])->size(TextSize::ExtraSmall)->color('gray'),
                ])->columnSpan(['default' => 9]),
            ]), $items);
    }

    /**
     * 9: en onemli (yoksa en yeni) duyuru tam metinle ustte; digerleri tek
     * satir baslik ve tarih.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<Component>
     */
    private function featured(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $rank = fn (array $item): int => match ($item['priority']) {
            AnnouncementPriority::Urgent => 2,
            AnnouncementPriority::Important => 1,
            AnnouncementPriority::Normal => 0,
        };
        $featuredIndex = 0;

        foreach ($items as $index => $item) {
            if ($rank($item) > $rank($items[$featuredIndex])) {
                $featuredIndex = $index;
            }
        }

        $featured = $items[$featuredIndex];
        $others = array_values(array_filter($items, fn (array $item, int $index): bool => $index !== $featuredIndex, ARRAY_FILTER_USE_BOTH));

        return [
            Callout::make($featured['title'])
                ->description($featured['body'])
                ->icon($featured['priority']->getIcon())
                ->color($featured['tone'])
                ->footer($featured['meta_full']),
            Text::make(__('ui_gallery.lists.others'))
                ->size(TextSize::ExtraSmall)
                ->weight(FontWeight::SemiBold)
                ->color('gray'),
            ...array_map(fn (array $item): Component => Flex::make([
                Text::make($item['title'])
                    ->icon($item['priority']->getIcon())
                    ->tooltip(Str::limit($item['body'], 160)),
                Text::make($item['day_short'])
                    ->size(TextSize::ExtraSmall)
                    ->color('gray')
                    ->grow(false),
            ])->extraAttributes(['class' => 'kc-list-row']), $others),
        ];
    }

    /**
     * 10: solda baslik listesi, sagda secilen duyurunun tam metni (dikey sekmeler).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<Component>
     */
    private function reader(array $items): array
    {
        if ($items === []) {
            return [];
        }

        return [
            Tabs::make('ui-list-reader')
                ->vertical()
                ->contained(false)
                // Baslik listesi %40, uzun baslik alt satira iner (konelsis.css).
                ->extraAttributes(['class' => 'kc-reader'])
                ->tabs(array_map(fn (array $item): Tab => Tab::make(Str::limit($item['title'], 60))
                    ->id('duyuru-'.$item['id'])
                    ->icon($item['priority']->getIcon())
                    ->badge($item['day_short'])
                    ->badgeColor($item['tone'])
                    ->schema([
                        Text::make($item['title'])->weight(FontWeight::Bold)->size(TextSize::Large),
                        Text::make($item['meta_full'])->size(TextSize::ExtraSmall)->color('gray'),
                        Text::make($item['body']),
                    ]), $items)),
        ];
    }
}
