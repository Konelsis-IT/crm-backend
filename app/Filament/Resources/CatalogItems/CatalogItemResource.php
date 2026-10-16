<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems;

use App\Filament\NavigationGroup;
use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\UsageArea;
use App\Exceptions\AbstractException;
use App\Filament\Resources\CatalogCategories\CatalogCategoryResource;
use App\Filament\Resources\CatalogItems\Pages\CreateCatalogItem;
use App\Filament\Resources\CatalogItems\Pages\EditCatalogItem;
use App\Filament\Resources\CatalogItems\Pages\ListCatalogItems;
use App\Filament\Resources\CatalogItems\Pages\ViewCatalogItem;
use App\Filament\Resources\CatalogItems\RelationManagers\CostLinesRelationManager;
use App\Filament\Resources\CatalogItems\RelationManagers\PriceHistoryRelationManager;
use App\Filament\Resources\CatalogItems\RelationManagers\PurchaseNeedsRelationManager;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\MoneyDisplay;
use App\Filament\Support\QuoteComparison;
use App\Filament\Support\UnitSelect;
use App\Models\Procurement\CatalogCategory;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Query\Procurement\CatalogQueries;
use App\Query\Procurement\CatalogScreenQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CatalogItemService;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Satin Alma > Katalog (B52, D-189). Sabit kalemler: Urun, Hizmet, Genel gider,
 * Idari kadro, Sirket ici (liste sekmeleri, ListCatalogItems). Kullanicinin en
 * buyuk kurali (D-187): Excel yuklemesi var olan kalemle eslesir, her seferinde
 * yeni kayit acilmaz; ayni turde ayni ad ikinci kez eklenemez
 * (CatalogItemService, CatalogItemExistsException). Kod kullanicidan alinmaz
 * (D-130). Silme yok, arsiv var (D-156).
 *
 * Kalem sayfasi: "Genel" (alanlar ve Excel esleme adlari); altta "Fiyat
 * gecmisi" (D-194: satin alma tekliflerindeki fiyatlari, B53), "Teklif ve
 * projeler" (kalemi isteyen teklif / proje maliyet satirlari) ve "Satin
 * alinacaklar". B53 oncesinde "Siparisler" sekmesi (kalem basina tedarikci
 * teklif kartlari) kalir.
 *
 * Birim (D-194, B54): Ayarlar > Birimler listesinden secilir ve zorunludur;
 * satin alma satirlarinda kalem secilince bu birim gelir ve kilitlenir.
 */
class CatalogItemResource extends Resource
{
    protected static ?string $model = CatalogItem::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    // D-192 menu sirasi: talepler 1, satin alinacaklar 2, teklifler 3, siparisler 4, sirket ici alimlar 5, liste 6, kategoriler 7, tedarik 8.
    protected static ?int $navigationSort = 6;

    // D-192: "Malzeme ve Hizmetler" kullanicinin yazdigi gibi ("Ve" degil).
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'catalog';

    public static function getModelLabel(): string
    {
        return __('catalog.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav');
    }

    public static function canAccess(): bool
    {
        return self::enabled() && parent::canAccess();
    }

    /** D-193: Kategoriler sayfasi da bu menu satirinin altindadir (ayri menu yok). */
    public static function getNavigationItemActiveRoutePattern(): string | array
    {
        return [static::getRouteBaseName().'.*', CatalogCategoryResource::getRouteBaseName().'.*'];
    }

    /** Katalog ozelligi acik ve B52 uygulandi mi? */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B52A') && FeatureFlags::enabled(Feature::Catalog);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('catalog.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    // Tur dugmeleri satir icinde; tur degisince kategori bosalir (agac ture gore).
                    ToggleButtons::make('kind')
                        ->label(__('catalog.fields.kind'))
                        ->options(CatalogItemKind::class)
                        ->default(CatalogItemKind::Product->value)
                        ->inline()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('catalog_category_id', null);
                        })
                        ->columnSpan(FieldGrid::HALF),
                    // D-190: ana kullanim yeri (tur degil); yalniz urun / hizmette.
                    ToggleButtons::make('usage_area')
                        ->label(__('catalog.fields.usage_area'))
                        ->helperText(__('catalog.help.usage_area'))
                        ->options(UsageArea::class)
                        ->inline()
                        ->visible(fn (Get $get): bool => (self::kindOf($get('kind')) ?? CatalogItemKind::Product)->hasUsageArea())
                        ->columnSpan(FieldGrid::NORMAL),
                    Select::make('catalog_category_id')
                        ->label(__('catalog.fields.category'))
                        ->options(fn (Get $get): array => app(CatalogQueries::class)->categoryOptions(self::kindOf($get('kind'))))
                        ->searchable()
                        ->native(false)
                        ->columnSpan(FieldGrid::NORMAL)
                        ->columnStart(1),
                    TextInput::make('name')
                        ->label(__('catalog.fields.name'))
                        ->required()
                        ->maxLength(300)
                        ->columnSpan(FieldGrid::WIDE),
                    // D-194 (B54): "Urun olusurken birimi belirlenmelidir" - sabit listeden, zorunlu.
                    UnitSelect::enabled()
                        ? UnitSelect::make(__('catalog.fields.unit'))
                            ->hintIcon(Heroicon::OutlinedInformationCircle)
                            ->hintIconTooltip(__('units.help.catalog_required'))
                            ->required()
                        : TextInput::make('unit')
                            ->label(__('catalog.fields.unit'))
                            ->maxLength(40)
                            ->columnSpan(FieldGrid::SHORT),
                    TextInput::make('brand')
                        ->label(__('catalog.fields.brand'))
                        ->maxLength(120)
                        ->columnSpan(FieldGrid::NORMAL)
                        ->columnStart(1),
                    Select::make('currency_code')
                        ->label(__('catalog.fields.currency'))
                        ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                        ->searchable()
                        ->native(false)
                        ->columnSpan(FieldGrid::NORMAL),
                    Textarea::make('specification')
                        ->label(__('catalog.fields.specification'))
                        ->rows(3)
                        ->maxLength(5000)
                        ->columnSpan(FieldGrid::LONG)
                        ->columnStart(1),
                ])),
            Section::make(__('catalog.sections.purchase'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Toggle::make('requires_approval')
                        ->label(__('catalog.fields.requires_approval'))
                        ->helperText(__('catalog.help.requires_approval'))
                        ->default(true)
                        ->columnSpan(FieldGrid::NORMAL),
                    TextInput::make('renewal_months')
                        ->label(__('catalog.fields.renewal_months'))
                        ->helperText(__('catalog.help.renewal_months'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(240)
                        ->suffix(__('catalog.values.months_suffix'))
                        ->columnSpan(FieldGrid::SHORT),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        // D-194: B53 ile eski "Siparisler" sekmesi (B52 kalem basina tedarikci
        // teklif kartlari) kalkar; fiyatlar "Fiyat gecmisi" listesindedir
        // (PriceHistoryRelationManager). Kullanici: "kaldirilan alani gostermek gereksiz".
        if (SchemaReadiness::hasBatch('B53')) {
            return $schema->columns(1)->components([
                Section::make(__('catalog.sections.general'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->columns(FieldGrid::COLUMNS)
                    ->schema(self::generalEntries()),
            ]);
        }

        $item = $schema->getRecord();
        $ordersVisible = FeatureFlags::enabled(Feature::PurchaseNeeds) && Gate::allows('viewAny', PurchaseNeed::class);

        return $schema->columns(1)->components([
            Tabs::make('catalog-item')
                ->id('catalog-item')
                ->persistTab()
                ->tabs([
                    Tab::make(__('catalog.sections.general'))
                        ->icon(Heroicon::OutlinedInformationCircle)
                        ->columns(FieldGrid::COLUMNS)
                        ->schema(self::generalEntries()),
                    Tab::make(__('catalog.sections.orders'))
                        ->icon(Heroicon::OutlinedShoppingBag)
                        ->visible($ordersVisible)
                        ->schema($ordersVisible && $item instanceof CatalogItem ? self::orderEntries($item) : []),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(CatalogScreenQueries::class)->itemList($query))
            ->columns([
                TextColumn::make('name')
                    ->label(__('catalog.fields.name'))
                    ->description(fn (CatalogItem $record): ?string => $record->category instanceof CatalogCategory ? $record->category->path() : null)
                    ->searchable(['name', 'normalized_name'])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('kind')
                    ->label(__('catalog.fields.kind'))
                    ->badge(),
                TextColumn::make('usage_area')
                    ->label(__('catalog.fields.usage_area'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('unit')
                    ->label(__('catalog.fields.unit'))
                    // D-194 (B54): sabit birimin adi; oncesinde serbest metin.
                    ->state(fn (CatalogItem $record): ?string => app(UnitResolver::class)->display($record))
                    ->placeholder('-'),
                IconColumn::make('requires_approval')
                    ->label(__('catalog.fields.requires_approval'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->falseColor('gray')
                    ->tooltip(fn (CatalogItem $record): string => (bool) $record->getAttribute('requires_approval')
                        ? __('catalog.values.approval_required')
                        : __('catalog.values.approval_not_required')),
                TextColumn::make('renewal_months')
                    ->label(__('catalog.fields.renewal_months'))
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? __('catalog.values.renewal', ['n' => (int) $state]) : '-')
                    ->placeholder('-'),
                TextColumn::make('cost_lines_count')
                    ->label(__('catalog.fields.cost_lines_count'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('needs_count')
                    ->label(__('catalog.fields.needs_count'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->label(__('catalog.fields.archived_at'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('archive')
                    ->label(__('catalog.filters.archive'))
                    ->options([
                        'active' => __('catalog.filters.archive_active'),
                        'archived' => __('catalog.filters.archive_archived'),
                        'all' => __('catalog.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(CatalogQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
                SelectFilter::make('kind')
                    ->label(__('catalog.filters.kind'))
                    ->options(CatalogItemKind::class)
                    ->native(false),
                SelectFilter::make('catalog_category_id')
                    ->label(__('catalog.filters.category'))
                    ->options(fn (): array => app(CatalogQueries::class)->categoryOptions())
                    ->searchable()
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (CatalogItem $record): bool => Gate::allows('update', $record)),
                self::archiveAction(),
                self::restoreAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('catalog.empty'))
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2)
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [
            // D-194: fiyat gecmisi (B53 satin alma teklifleri) ilk sekme.
            PriceHistoryRelationManager::class,
            CostLinesRelationManager::class,
            PurchaseNeedsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCatalogItems::route('/'),
            'create' => CreateCatalogItem::route('/create'),
            'view' => ViewCatalogItem::route('/{record}'),
            'edit' => EditCatalogItem::route('/{record}/edit'),
        ];
    }

    /** Arsive al (D-156): duz onay, sebep yok; tablo satirinda ve kalem sayfasinin basliginda. */
    public static function archiveAction(): Action
    {
        return Action::make('archive_catalog_item')
            ->label(__('catalog.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('catalog.actions.archive'))
            ->modalDescription(__('catalog.help.archive'))
            ->modalSubmitActionLabel(__('catalog.actions.archive'))
            ->visible(fn (CatalogItem $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (CatalogItem $record): void {
                try {
                    app(CatalogItemService::class)->archive($record);
                    DomainNotifications::success(__('catalog.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $record->refresh();
            });
    }

    public static function restoreAction(): Action
    {
        return Action::make('restore_catalog_item')
            ->label(__('catalog.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->modalHeading(__('catalog.actions.restore'))
            ->modalDescription(__('catalog.help.restore'))
            ->visible(fn (CatalogItem $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (CatalogItem $record): void {
                try {
                    app(CatalogItemService::class)->restore($record);
                    DomainNotifications::success(__('catalog.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $record->refresh();
            });
    }

    /** Tur alaninin durumu (ToggleButtons enum ornegi dondurur, FormState ile okunur). */
    public static function kindOf(mixed $state): ?CatalogItemKind
    {
        $value = FormState::value($state);

        return $value === null ? null : CatalogItemKind::tryFrom($value);
    }

    /**
     * "Genel" sekmesi: kalemin alanlari ve Excel esleme adlari.
     *
     * @return list<mixed>
     */
    private static function generalEntries(): array
    {
        return FieldGrid::fields([
            TextEntry::make('kind')
                ->label(__('catalog.fields.kind'))
                ->badge()
                ->columnSpan(FieldGrid::SHORT),
            TextEntry::make('usage_area')
                ->label(__('catalog.fields.usage_area'))
                ->badge()
                ->placeholder('-')
                ->columnSpan(FieldGrid::SHORT),
            TextEntry::make('category_path')
                ->label(__('catalog.fields.category'))
                ->state(fn (CatalogItem $record): ?string => $record->category instanceof CatalogCategory ? $record->category->path() : null)
                ->placeholder('-')
                ->columnSpan(FieldGrid::WIDE),
            // Ad sayfanin alt basliginda; kartta tekrar yazilmaz (D-205).
            TextEntry::make('unit')
                ->label(__('catalog.fields.unit'))
                ->state(fn (CatalogItem $record): ?string => app(UnitResolver::class)->display($record))
                ->placeholder('-')
                ->columnSpan(FieldGrid::SHORT)
                ->columnStart(1),
            MoneyDisplay::currencyEntry('currency_code')
                ->label(__('catalog.fields.currency'))
                ->placeholder('-')
                ->columnSpan(FieldGrid::NORMAL),
            TextEntry::make('brand')
                ->label(__('catalog.fields.brand'))
                ->placeholder('-')
                ->columnSpan(FieldGrid::NORMAL),
            TextEntry::make('requires_approval')
                ->label(__('catalog.fields.requires_approval'))
                ->formatStateUsing(fn (mixed $state): string => (bool) $state ? __('catalog.values.approval_required') : __('catalog.values.approval_not_required'))
                ->badge()
                ->color(fn (mixed $state): string => (bool) $state ? 'warning' : 'gray')
                ->icon(fn (mixed $state): Heroicon => (bool) $state ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedMinusCircle)
                ->columnSpan(FieldGrid::SHORT)
                ->columnStart(1),
            TextEntry::make('renewal_months')
                ->label(__('catalog.fields.renewal_months'))
                ->formatStateUsing(fn (mixed $state): string => filled($state) ? __('catalog.values.renewal', ['n' => (int) $state]) : '-')
                ->placeholder('-')
                ->columnSpan(FieldGrid::SHORT),
            TextEntry::make('specification')
                ->label(__('catalog.fields.specification'))
                ->placeholder('-')
                ->columnSpan(FieldGrid::LONG)
                ->columnStart(1),
            TextEntry::make('aliases.alias')
                ->label(__('catalog.fields.aliases'))
                ->helperText(__('catalog.help.aliases'))
                ->badge()
                ->color('gray')
                ->placeholder(__('catalog.values.no_aliases'))
                ->columnSpan(FieldGrid::LONG),
        ]);
    }

    /**
     * "Siparisler" sekmesi: kisa ozet satiri ve butun tedarikci tekliflerinin
     * karsilastirma kartlari (farkli miktarlar birim fiyatla karsilastirilir).
     *
     * @return list<mixed>
     */
    private static function orderEntries(CatalogItem $item): array
    {
        $orders = app(CatalogScreenQueries::class)->itemQuotes($item);
        $best = $orders['best'];

        if ($orders['needs'] === 0) {
            return [
                TextEntry::make('orders_empty')
                    ->hiddenLabel()
                    ->state(__('catalog.orders.none'))
                    ->color('gray'),
            ];
        }

        $summary = $best instanceof PurchaseQuote
            ? __('catalog.orders.summary', [
                'count' => $orders['needs'],
                'price' => Money::format($best->getAttribute('unit_price'), $best->getAttribute('currency_code'), '-', 4),
            ])
            : __('catalog.orders.summary_no_quote', ['count' => $orders['needs']]);

        return [
            TextEntry::make('orders_summary')
                ->hiddenLabel()
                ->state($summary)
                ->icon(Heroicon::OutlinedShoppingBag)
                ->weight('bold'),
            ...QuoteComparison::cards($orders['quotes'], byUnitPrice: true, showNeed: true),
        ];
    }
}
