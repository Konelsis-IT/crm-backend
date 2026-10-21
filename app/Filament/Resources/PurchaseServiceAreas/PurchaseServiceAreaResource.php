<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseServiceAreas;

use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\PurchaseServiceAreas\Pages\ManagePurchaseServiceAreas;
use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\RowDetail;
use App\Models\Procurement\PurchaseServiceArea;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\PurchaseServiceAreaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
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
 * Tedarikci hizmet alanlari (D-232, B58): Excel'in grup basliklari (Salt ve
 * Otomasyon, Kablo Ureticileri...). Sol menude ayri satir yok; Tedarikciler
 * ekraninin basligindaki "Hizmet alanlari" dugmesiyle acilir, menude
 * Tedarikciler secili kalir. Ekleme ve duzenleme pencerede; silme yok, arsiv var.
 */
class PurchaseServiceAreaResource extends Resource
{
    protected static ?string $model = PurchaseServiceArea::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'tedarikci-hizmet-alanlari';

    public static function getModelLabel(): string
    {
        return __('purchase_supplier.area.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_supplier.area.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_supplier.area.plural');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return SchemaReadiness::hasBatch('B58')
            && FeatureFlags::enabled(Feature::PurchaseSuppliers)
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
            TextInput::make('name')
                ->label(__('purchase_supplier.area.fields.name'))
                ->required()
                ->maxLength(150),
            TextInput::make('sort_order')
                ->label(__('purchase_supplier.area.fields.sort_order'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(100000),
            Hidden::make('row_version'),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('suppliers'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('purchase_supplier.area.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('suppliers_count')
                    ->label(__('purchase_supplier.area.fields.suppliers_count'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('purchase_supplier.area.fields.sort_order'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('archive')
                    ->label(__('purchase_supplier.filters.archive'))
                    ->options([
                        'active' => __('purchase_supplier.filters.archive_active'),
                        'archived' => __('purchase_supplier.filters.archive_archived'),
                        'all' => __('purchase_supplier.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => match ((string) ($data['value'] ?? 'active')) {
                        'archived' => $query->whereNotNull('archived_at'),
                        'all' => $query,
                        default => $query->whereNull('archived_at'),
                    }),
            ])
            ->recordActions([
                RowDetail::action(),
                self::editAction(),
                self::archiveAction(),
                self::restoreAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('purchase_supplier.area.empty'))
            ->emptyStateIcon(Heroicon::OutlinedTag)
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchaseServiceAreas::route('/'),
        ];
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label(__('purchase_supplier.area.actions.create'))
            ->modalHeading(__('purchase_supplier.area.actions.create'))
            ->modalWidth(Width::FourExtraLarge)
            ->using(function (array $data): Model {
                try {
                    return app(PurchaseServiceAreaService::class)->create($data);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    throw new Halt;
                }
            })
            ->successNotificationTitle(__('purchase_supplier.area.messages.created'));
    }

    public static function editAction(): EditAction
    {
        return EditAction::make()
            ->modalHeading(__('purchase_supplier.area.actions.edit'))
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (PurchaseServiceArea $record): bool => Gate::allows('update', $record))
            ->using(function (PurchaseServiceArea $record, array $data): Model {
                try {
                    return app(PurchaseServiceAreaService::class)->update($record, $data);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    throw new Halt;
                }
            })
            ->successNotificationTitle(__('purchase_supplier.area.messages.saved'));
    }

    public static function archiveAction(): Action
    {
        return Action::make('archive_service_area')
            ->label(__('purchase_supplier.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('purchase_supplier.actions.archive'))
            ->modalDescription(__('purchase_supplier.area.modals.archive_description'))
            ->modalSubmitActionLabel(__('purchase_supplier.actions.archive'))
            ->visible(fn (PurchaseServiceArea $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (PurchaseServiceArea $record): void {
                try {
                    app(PurchaseServiceAreaService::class)->archive($record);
                    DomainNotifications::success(__('purchase_supplier.area.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function restoreAction(): Action
    {
        return Action::make('restore_service_area')
            ->label(__('purchase_supplier.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->modalHeading(__('purchase_supplier.actions.restore'))
            ->modalDescription(__('purchase_supplier.area.modals.restore_description'))
            ->visible(fn (PurchaseServiceArea $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->action(function (PurchaseServiceArea $record): void {
                try {
                    app(PurchaseServiceAreaService::class)->restore($record);
                    DomainNotifications::success(__('purchase_supplier.area.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Menude Tedarikciler secili kalir. */
    public static function getNavigationItemActiveRoutePattern(): string | array
    {
        return PurchaseSupplierResource::getRouteBaseName().'.*';
    }
}
