<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Project\ProjectStatus;
use App\Filament\Clusters\UiGallery;
use App\Filament\Concerns\InteractsWithCardView;
use App\Filament\Contracts\HasCardView;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\CardGallery;
use App\Models\Document\Document;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Query\Ui\CardGalleryQueries;
use App\Query\Ui\RecordCardQueries;
use App\Services\Authorization\RoleResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;

/**
 * UI deneme: yeniden kullanilabilir kayit karti tasarimi (10 Eylul 2026).
 * Kalici bilesen katalogudur: buraya eklenen hicbir sey silinmez, yeni
 * varyantlar yanina eklenir. Yalniz sistem yoneticisi gorur.
 *
 * A sekmesi: sema kartlari (Section tabanli, tam serbest yerlesim) — kapakli
 *   varyant 10 Eylul 2026'da secildi (D-79).
 * B sekmesi: tablo kartlari (contentGrid; arama, suzgec, siralama, sayfalama
 *   ve satir eylemleri tabloda kalir).
 * C sekmesi: A'nin kapakli karti, listelerde kullanildigi haliyle — arama,
 *   sayfa boyu ve sayfalama (CardList + InteractsWithCardView).
 */
class UiCardGallery extends Page implements HasCardView, HasTable
{
    use InteractsWithCardView;
    use InteractsWithTable;

    public const SOURCE_PROJECTS = 'projects';

    public const SOURCE_PERSONNEL = 'personnel';

    public const SOURCE_DOCUMENTS = 'documents';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 1;

    #[Session(key: 'konelsis.ui-gallery.variant')]
    public string $variant = CardGallery::VARIANT_COVER;

    /** C sekmesinde hangi kaynagin kartlari listelenir. */
    #[Url(as: 'kaynak', except: self::SOURCE_DOCUMENTS)]
    public string $cardSource = self::SOURCE_DOCUMENTS;

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.cards');
    }

    public function getTitle(): string
    {
        return __('ui_gallery.title');
    }

    public function getSubheading(): ?string
    {
        return __('ui_gallery.subheading');
    }

    /**
     * Calisma arayuzu (D-91): yalniz gelistirme ortaminda menude gorunur,
     * production'da hic kaydedilmez. Ayrica tam yetkili rol ister.
     */
    public static function canAccess(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof Personnel && app(RoleResolver::class)->hasFullAccess($user);
    }

    /**
     * Yerlesimi degistirir. Sayfa icerigi (content semasi) bilesen kurulurken
     * bir kez uretildigi icin secim oturuma yazilip sayfa yeniden acilir.
     */
    public function setVariant(string $variant): void
    {
        $this->variant = in_array($variant, [CardGallery::VARIANT_COVER, CardGallery::VARIANT_ROW], true)
            ? $variant
            : CardGallery::VARIANT_COVER;

        $this->redirect(static::getUrl());
    }

    public function updatedCardSource(): void
    {
        $this->cardPage = 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('variant_cover')
                ->label(__('ui_gallery.variants.cover'))
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color(fn (): string => $this->variant === CardGallery::VARIANT_COVER ? 'primary' : 'gray')
                ->action(fn () => $this->setVariant(CardGallery::VARIANT_COVER)),
            Action::make('variant_row')
                ->label(__('ui_gallery.variants.row'))
                ->icon(Heroicon::OutlinedBars3BottomLeft)
                ->color(fn (): string => $this->variant === CardGallery::VARIANT_ROW ? 'primary' : 'gray')
                ->action(fn () => $this->setVariant(CardGallery::VARIANT_ROW)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $gallery = app(CardGallery::class);
        $queries = app(CardGalleryQueries::class);
        $variant = $this->variant;

        $projects = $queries->projects()->map(fn (Project $project) => $gallery->projectCard($project, $variant))->all();
        $personnel = $queries->personnel()->map(fn (Personnel $person) => $gallery->personnelCard($person, $variant))->all();
        $documents = $queries->documents()->map(fn ($document) => $gallery->documentCard($document, $variant))->all();
        $servers = array_map(fn (array $server) => $gallery->serverCard($server, $variant), $this->sampleServers());

        return $schema->columns(1)->components([
            Callout::make(__('ui_gallery.help.intro'))->info()->icon(Heroicon::OutlinedSwatch),
            Tabs::make('ui-card-gallery')
                ->id('ui-card-gallery')
                ->persistTab()
                ->contained(false)
                ->tabs([
                    Tab::make(__('ui_gallery.tabs.schema'))
                        ->icon(Heroicon::OutlinedSquares2x2)
                        ->schema([
                            Callout::make(__('ui_gallery.help.schema'))->color('gray'),
                            Section::make(__('ui_gallery.sections.projects'))
                                ->icon(Heroicon::OutlinedRocketLaunch)
                                ->afterHeader([Text::make((string) count($projects))->badge()->color('gray')])
                                ->schema([$gallery->grid($projects, $variant)])
                                ->collapsible(),
                            Section::make(__('ui_gallery.sections.personnel'))
                                ->icon(Heroicon::OutlinedUsers)
                                ->afterHeader([Text::make((string) count($personnel))->badge()->color('gray')])
                                ->schema([$gallery->grid($personnel, $variant)])
                                ->collapsible(),
                            Section::make(__('ui_gallery.sections.documents'))
                                ->icon(Heroicon::OutlinedDocumentText)
                                ->afterHeader([Text::make((string) count($documents))->badge()->color('gray')])
                                ->schema([$gallery->grid($documents, $variant)])
                                ->collapsible(),
                            Section::make(__('ui_gallery.sections.servers'))
                                ->icon(Heroicon::OutlinedServerStack)
                                ->description(__('ui_gallery.help.servers_mock'))
                                ->afterHeader([Text::make((string) count($servers))->badge()->color('gray')])
                                ->schema([$gallery->grid($servers, $variant)])
                                ->collapsible(),
                        ]),
                    Tab::make(__('ui_gallery.tabs.table'))
                        ->icon(Heroicon::OutlinedTableCells)
                        ->schema([
                            Callout::make(__('ui_gallery.help.table'))->color('gray'),
                            EmbeddedTable::make(),
                        ]),
                    Tab::make(__('ui_gallery.tabs.list'))
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->schema([
                            Callout::make(__('ui_gallery.help.list'))->color('gray'),
                            Grid::make(['default' => 1, 'md' => 3])->components([
                                Select::make('cardSource')
                                    ->label(__('ui_gallery.list.source'))
                                    ->options([
                                        self::SOURCE_PROJECTS => __('ui_gallery.sections.projects'),
                                        self::SOURCE_PERSONNEL => __('ui_gallery.sections.personnel'),
                                        self::SOURCE_DOCUMENTS => __('ui_gallery.sections.documents'),
                                    ])
                                    ->selectablePlaceholder(false)
                                    ->live(),
                            ]),
                            ...$this->cardListComponents(__('ui_gallery.list.search')),
                        ]),
                ]),
        ]);
    }

    protected function cardPaginator(): LengthAwarePaginator
    {
        $queries = app(RecordCardQueries::class);
        $search = $this->cardSearchTerm();
        $perPage = $this->cardPerPageValue();
        $page = $this->cardPageValue();

        return match ($this->cardSource) {
            self::SOURCE_PROJECTS => $queries->projects($search, $perPage, $page),
            self::SOURCE_PERSONNEL => $queries->personnel($search, $perPage, $page),
            default => $queries->documents($search, $perPage, $page),
        };
    }

    protected function cardFor(Model $record): Component
    {
        $gallery = app(CardGallery::class);

        return match (true) {
            $record instanceof Project => $gallery->projectCard($record, $this->variant),
            $record instanceof Personnel => $gallery->personnelCard($record, $this->variant),
            $record instanceof Document => $gallery->documentCard($record, $this->variant),
            default => throw new \InvalidArgumentException('Kart tanimsiz kayit turu: '.$record::class),
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => app(CardGalleryQueries::class)->projectsForTable())
            ->columns(app(CardGallery::class)->projectTableCardColumns())
            ->contentGrid(['md' => 2, 'xl' => 3, '2xl' => 4])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('project.fields.status'))
                    ->options(ProjectStatus::class),
            ])
            ->recordActions([
                ViewAction::make()->url(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record])),
                EditAction::make()->url(fn (Project $record): string => ProjectResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->paginated([8, 16, 32])
            ->defaultPaginationPageOption(8)
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('ui_gallery.values.empty'))
            ->emptyStateIcon(Heroicon::OutlinedRocketLaunch);
    }

    /**
     * Sunucu modulu henuz yok; kart anatomisini gostermek icin ornek veri.
     *
     * @return list<array{name: string, environment: string, status: string, ip: string, cpu: int, ram: int, disk: int, uptime: string, location: string, checked_at: string}>
     */
    private function sampleServers(): array
    {
        return [
            ['name' => 'KNL-APP-01', 'environment' => 'production', 'status' => 'running', 'ip' => '10.0.10.11', 'cpu' => 38, 'ram' => 62, 'disk' => 47, 'uptime' => '41 gün 6 saat', 'location' => 'İstanbul DC', 'checked_at' => 'az önce'],
            ['name' => 'KNL-DB-01', 'environment' => 'production', 'status' => 'warning', 'ip' => '10.0.10.21', 'cpu' => 71, 'ram' => 88, 'disk' => 79, 'uptime' => '112 gün 2 saat', 'location' => 'İstanbul DC', 'checked_at' => '1 dk önce'],
            ['name' => 'KNL-SCADA-GW', 'environment' => 'production', 'status' => 'running', 'ip' => '10.0.20.5', 'cpu' => 22, 'ram' => 41, 'disk' => 33, 'uptime' => '9 gün 14 saat', 'location' => 'Karapınar saha', 'checked_at' => '2 dk önce'],
            ['name' => 'KNL-TEST-01', 'environment' => 'test', 'status' => 'running', 'ip' => '10.0.30.11', 'cpu' => 12, 'ram' => 35, 'disk' => 58, 'uptime' => '3 gün 1 saat', 'location' => 'Ankara ofis', 'checked_at' => '5 dk önce'],
            ['name' => 'KNL-BACKUP', 'environment' => 'production', 'status' => 'down', 'ip' => '10.0.10.90', 'cpu' => 0, 'ram' => 0, 'disk' => 91, 'uptime' => '-', 'location' => 'İstanbul DC', 'checked_at' => '18 dk önce'],
            ['name' => 'KNL-CAM-NVR', 'environment' => 'production', 'status' => 'running', 'ip' => '10.0.20.40', 'cpu' => 54, 'ram' => 66, 'disk' => 84, 'uptime' => '27 gün 9 saat', 'location' => 'Karapınar saha', 'checked_at' => 'az önce'],
        ];
    }
}
