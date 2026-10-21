<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogCategories;

use App\Filament\NavigationGroup;
use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Exceptions\AbstractException;
use App\Filament\Resources\CatalogCategories\Pages\ManageCatalogCategories;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\RowDetail;
use App\Models\Procurement\CatalogCategory;
use App\Query\Procurement\CatalogQueries;
use App\Query\Procurement\CatalogScreenQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CatalogCategoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Satin Alma > Katalog kategorileri (B52, D-189): tur basina agac (Excel'in
 * bolum > grup yolu: Insaat › Saha Duzeltme). Urun ve Hizmet ayni agaci
 * kullanir. Tek liste sayfasi, tur sekmeleri; ekleme ve duzenleme pencerede,
 * yazma CatalogCategoryService ile. Kod kullanicidan alinmaz (D-130). Silme
 * yok, arsiv var (D-156); satira tiklamak salt okunur ayrinti penceresi (D-125).
 *
 * D-228 ek (19 Ekim 2026, kullanici: "idari kadronun ve genel giderin
 * kategorileri kendi menusu icinde olmali. Malzeme ve Hizmetler tarafini
 * acmamali"): bu sayfa yalniz Urun / Hizmet agaci; Genel gider ve Idari kadro
 * kategorileri kendi sayfalarinda (GeneralExpenseCategoryResource,
 * AdminStaffCategoryResource), kendi menu satirlari secili. `kinds()` sayfanin
 * agaclari; tek agacta tur alani, sutunu ve sekmeleri yok.
 */
class CatalogCategoryResource extends Resource
{
    protected static ?string $model = CatalogCategory::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    protected static ?int $navigationSort = 7;

    // D-192: "Kategoriler" kullanicinin yazdigi gibi ("Ve" degil).
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'catalog-categories';

    public static function getModelLabel(): string
    {
        return __('catalog.category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.category.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.category.nav');
    }

    /**
     * D-193 (kullanici 12 Ekim 2026: "Kategorileri gereken yere tasiyalim"):
     * sol menude ayri satir yok; Malzeme ve Hizmetler ekraninin basligindaki
     * "Kategoriler" dugmesiyle acilir, menude Malzeme ve Hizmetler secili kalir.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return SchemaReadiness::hasBatch('B52A')
            && FeatureFlags::enabled(Feature::Catalog)
            && parent::canAccess();
    }

    /**
     * Sayfanin kategori agaclari (D-228 ek). Urun ve Hizmet tek agac (Urun).
     *
     * @return list<CatalogItemKind>
     */
    public static function kinds(): array
    {
        return [CatalogItemKind::Product];
    }

    public static function singleKind(): bool
    {
        return count(static::kinds()) === 1;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('kind', array_map(
            static fn (CatalogItemKind $kind): string => $kind->value,
            static::kinds(),
        ));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
            Select::make('kind')
                ->label(__('catalog.category.fields.kind'))
                ->helperText(__('catalog.category.help.kind'))
                ->options(static::kindOptions())
                ->default(static::kinds()[0]->value)
                // D-228 ek: tek agacli sayfada tur sabit (gizli, yine kaydedilir).
                ->hidden(static::singleKind())
                ->dehydratedWhenHidden()
                ->required()
                ->native(false)
                ->live()
                ->disabledOn('edit')
                ->afterStateUpdated(function (Set $set): void {
                    $set('parent_id', null);
                }),
            Select::make('parent_id')
                ->label(__('catalog.category.fields.parent'))
                ->helperText(__('catalog.category.help.parent'))
                ->options(fn (Get $get, ?CatalogCategory $record): array => app(CatalogScreenQueries::class)->parentOptions(
                    self::kindOf($get('kind')) ?? ($record?->getAttribute('kind') instanceof CatalogItemKind ? $record->getAttribute('kind') : null),
                    $record !== null ? (int) $record->getKey() : null,
                ))
                ->searchable()
                ->native(false),
            TextInput::make('name')
                ->label(__('catalog.category.fields.name'))
                ->required()
                ->maxLength(200)
                ->columnStart(1),
            Hidden::make('row_version'),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(CatalogScreenQueries::class)->categoryList($query))
            ->columns([
                TextColumn::make('name')
                    ->label(__('catalog.category.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label(__('catalog.category.fields.parent'))
                    ->placeholder('-'),
                TextColumn::make('kind')
                    ->label(__('catalog.category.fields.kind'))
                    ->badge()
                    ->visible(! static::singleKind()),
                TextColumn::make('items_count')
                    ->label(__('catalog.category.fields.items_count'))
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
            ])
            ->recordActions([
                RowDetail::action(),
                self::editAction(),
                self::archiveAction(),
                self::restoreAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('catalog.category.empty'))
            ->emptyStateIcon(Heroicon::OutlinedFolder)
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCatalogCategories::route('/'),
        ];
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label(__('catalog.category.actions.create'))
            ->modalHeading(__('catalog.category.actions.create'))
            ->modalWidth(Width::FourExtraLarge)
            // Acik sekmenin turuyle acilir.
            ->fillForm(fn (mixed $livewire): array => [
                'kind' => is_object($livewire) && property_exists($livewire, 'activeTab') && array_key_exists((string) $livewire->activeTab, static::kindOptions())
                    ? (string) $livewire->activeTab
                    : static::kinds()[0]->value,
            ])
            ->using(function (array $data): Model {
                try {
                    return app(CatalogCategoryService::class)->create($data);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    throw new Halt;
                }
            })
            ->successNotificationTitle(__('catalog.category.messages.created'));
    }

    public static function editAction(): EditAction
    {
        return EditAction::make()
            ->modalHeading(__('catalog.category.actions.edit'))
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (CatalogCategory $record): bool => Gate::allows('update', $record))
            ->using(function (CatalogCategory $record, array $data): Model {
                try {
                    return app(CatalogCategoryService::class)->update($record, $data);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    throw new Halt;
                }
            })
            ->successNotificationTitle(__('catalog.category.messages.saved'));
    }

    public static function archiveAction(): Action
    {
        return Action::make('archive_catalog_category')
            ->label(__('catalog.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('catalog.actions.archive'))
            ->modalDescription(__('catalog.category.help.archive'))
            ->modalSubmitActionLabel(__('catalog.actions.archive'))
            ->visible(fn (CatalogCategory $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (CatalogCategory $record): void {
                try {
                    app(CatalogCategoryService::class)->archive($record);
                    DomainNotifications::success(__('catalog.category.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function restoreAction(): Action
    {
        return Action::make('restore_catalog_category')
            ->label(__('catalog.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->modalHeading(__('catalog.actions.restore'))
            ->modalDescription(__('catalog.category.help.restore'))
            ->visible(fn (CatalogCategory $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (CatalogCategory $record): void {
                try {
                    app(CatalogCategoryService::class)->restore($record);
                    DomainNotifications::success(__('catalog.category.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Kategori turleri: Urun ve Hizmet tek agac ("Urun / Hizmet").
     *
     * @return array<string, string>
     */
    public static function kindOptions(): array
    {
        $options = [];

        foreach (static::kinds() as $kind) {
            if ($kind === CatalogItemKind::Service) {
                continue;
            }

            $options[$kind->value] = $kind === CatalogItemKind::Product
                ? __('catalog.category.tabs.product')
                : (string) $kind->getLabel();
        }

        return $options;
    }

    private static function kindOf(mixed $state): ?CatalogItemKind
    {
        $value = FormState::value($state);

        return $value === null ? null : CatalogItemKind::tryFrom($value);
    }
}
