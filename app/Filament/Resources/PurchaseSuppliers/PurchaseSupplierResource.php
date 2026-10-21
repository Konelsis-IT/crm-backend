<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers;

use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\PurchaseServiceAreas\PurchaseServiceAreaResource;
use App\Filament\Resources\PurchaseSuppliers\Pages\CreatePurchaseSupplier;
use App\Filament\Resources\PurchaseSuppliers\Pages\EditPurchaseSupplier;
use App\Filament\Resources\PurchaseSuppliers\Pages\ListPurchaseSuppliers;
use App\Filament\Resources\PurchaseSuppliers\Pages\ViewPurchaseSupplier;
use App\Filament\Resources\PurchaseSuppliers\RelationManagers\SupplierAddressesRelationManager;
use App\Filament\Resources\PurchaseSuppliers\RelationManagers\SupplierContactsRelationManager;
use App\Filament\Resources\PurchaseSuppliers\Schemas\PurchaseSupplierInfolist;
use App\Filament\Support\ActionColors;
use App\Filament\Support\CodeLine;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseSupplier;
use App\Query\Party\PartyQueries;
use App\Query\Procurement\PurchaseSupplierQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\PurchaseSupplierService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Operasyon - Satin alma > Tedarikciler (D-232, B58; kullanici 21 Ekim 2026:
 * "Satın alma için ayrı bir tedarikçi listesi oluşturalım bunun yapısı normal
 * taraflar ile aynı olacak ama menü olarak satın alma kısmında olacak. Hangi
 * alanda hizmet verdikleri de belli").
 *
 * Kayit bir taraftir (ayni form: kisa ad / uzun ad, ulke, iletisim kanallari;
 * Kisiler ve Adresler sekmeleri), uyelik `purchase_suppliers` tablosundadir:
 * hizmet alanlari, not, arsiv. Yalniz tedarikci olan firma Taraflar'da gorunmez
 * (HidePurchaseOnlyPartiesScope); Taraflar'da da olan firma iki listede de
 * gorunur ("Taraflarda da" rozeti). Yetki: tedarikci politikasi (Malzeme ve
 * Hizmetler yetkisi), taraf yetkisi gerekmez.
 */
class PurchaseSupplierResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    // D-192 sirasi: ... Malzeme ve Hizmetler 6; Tedarikciler 7.
    protected static ?int $navigationSort = 7;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?string $slug = 'tedarikciler';

    public static function getModelLabel(): string
    {
        return __('purchase_supplier.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_supplier.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_supplier.nav');
    }

    public static function canAccess(): bool
    {
        return SchemaReadiness::hasBatch('B58')
            && FeatureFlags::enabled(Feature::PurchaseSuppliers)
            && static::canViewAny();
    }

    /**
     * Yetki tedarikci uyeligi uzerinden (taraf yetkisi degil): goruntuleme ve
     * ekleme tedarikci politikasi, duzenleme kaydin uyeligi; silme yok.
     */
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if (! PurchaseSupplierQueries::enabled()) {
            return Response::deny();
        }

        $ability = $action instanceof BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);
        $membership = $record instanceof Party ? self::membership($record) : null;

        return match ($ability) {
            'viewAny' => Gate::inspect('viewAny', PurchaseSupplier::class),
            'create' => Gate::inspect('create', PurchaseSupplier::class),
            'view' => $membership !== null ? Gate::inspect('view', $membership) : Response::deny(),
            'update' => $membership !== null ? Gate::inspect('update', $membership) : Response::deny(),
            default => Response::deny(),
        };
    }

    /** Hizmet alanlari sayfasi da bu menu satirinda secili kalir. */
    public static function getNavigationItemActiveRoutePattern(): string | array
    {
        return [static::getRouteBaseName().'.*', PurchaseServiceAreaResource::getRouteBaseName().'.*'];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(PurchaseSupplierQueries::class)->base();
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['display_name', 'organizationProfile.legal_name', 'organizationProfile.trade_name'];
    }

    public static function form(Schema $schema): Schema
    {
        return PartyResource::partyForm($schema, supplier: true, extraSections: [
            Section::make(__('purchase_supplier.sections.supplier'))
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('purchase_supplier.service_area_ids')
                        ->label(__('purchase_supplier.fields.service_areas'))
                        ->helperText(__('purchase_supplier.help.service_areas'))
                        ->options(fn (): array => app(PurchaseSupplierQueries::class)->areaOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->columnSpan(FieldGrid::WIDE),
                    Textarea::make('purchase_supplier.note')
                        ->label(__('purchase_supplier.fields.note'))
                        ->helperText(__('purchase_supplier.help.note'))
                        ->rows(3)
                        ->maxLength(4000)
                        ->columnSpan(FieldGrid::LONG)
                        ->columnStart(1),
                ])),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseSupplierInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => app(PurchaseSupplierQueries::class)->withListRelations($query))
            ->columns([
                CodeLine::under(
                    TextColumn::make('display_name')
                        ->label(__('purchase_supplier.fields.name'))
                        ->limit(40)
                        ->searchable(query: fn (Builder $query, string $search): Builder => app(PartyQueries::class)->searchByName($query, $search))
                        ->sortable(),
                    'party_no',
                ),
                TextColumn::make('service_areas')
                    ->label(__('purchase_supplier.fields.service_areas'))
                    ->state(fn (Party $record): array => $record->purchaseSupplier?->serviceAreas->pluck('name')->all() ?? [])
                    ->badge()
                    ->color('gray')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('-'),
                TextColumn::make('contacts_count')
                    ->label(__('purchase_supplier.fields.contacts_count'))
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('shared')
                    ->label(__('purchase_supplier.fields.shared'))
                    ->state(fn (Party $record): ?string => $record->purchaseSupplier !== null && ! (bool) $record->purchaseSupplier->getAttribute('purchase_only') ? __('purchase_supplier.values.shared') : null)
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),
                TextColumn::make('purchaseSupplier.note')
                    ->label(__('purchase_supplier.fields.note'))
                    ->limit(60)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchaseSupplier.archived_at')
                    ->label(__('purchase_supplier.fields.archived_at'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->query(fn (Builder $query, array $data): Builder => app(PurchaseSupplierQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
                SelectFilter::make('areas')
                    ->label(__('purchase_supplier.filters.areas'))
                    ->options(fn (): array => app(PurchaseSupplierQueries::class)->areaOptions())
                    ->multiple()
                    ->searchable()
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(PurchaseSupplierQueries::class)->inAreas($query, (array) ($data['values'] ?? []))),
                TernaryFilter::make('shared')
                    ->label(__('purchase_supplier.filters.shared'))
                    ->queries(
                        true: fn (Builder $query): Builder => app(PurchaseSupplierQueries::class)->shared($query, true),
                        false: fn (Builder $query): Builder => app(PurchaseSupplierQueries::class)->shared($query, false),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Party $record): bool => static::canEdit($record)),
                self::archiveAction(),
                self::restoreAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('purchase_supplier.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBuildingStorefront)
            ->defaultSort('display_name')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [
            SupplierContactsRelationManager::class,
            SupplierAddressesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseSuppliers::route('/'),
            'create' => CreatePurchaseSupplier::route('/create'),
            'view' => ViewPurchaseSupplier::route('/{record}'),
            'edit' => EditPurchaseSupplier::route('/{record}/edit'),
        ];
    }

    /** Arsive al (D-156): yalniz tedarikci uyeligi; Taraflar'daki kayit etkilenmez. */
    public static function archiveAction(): Action
    {
        return Action::make('archive_supplier')
            ->label(__('purchase_supplier.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('purchase_supplier.actions.archive'))
            ->modalDescription(__('purchase_supplier.modals.archive_description'))
            ->modalSubmitActionLabel(__('purchase_supplier.actions.archive'))
            ->visible(fn (Party $record): bool => ($membership = self::membership($record)) !== null && ! $membership->isArchived() && Gate::allows('archive', $membership))
            ->action(function (Party $record): void {
                try {
                    app(PurchaseSupplierService::class)->archive(self::membership($record));
                    DomainNotifications::success(__('purchase_supplier.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $record->unsetRelation('purchaseSupplier');
            });
    }

    public static function restoreAction(): Action
    {
        return Action::make('restore_supplier')
            ->label(__('purchase_supplier.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->modalHeading(__('purchase_supplier.actions.restore'))
            ->modalDescription(__('purchase_supplier.modals.restore_description'))
            ->visible(fn (Party $record): bool => ($membership = self::membership($record)) !== null && $membership->isArchived() && Gate::allows('archive', $membership))
            ->action(function (Party $record): void {
                try {
                    app(PurchaseSupplierService::class)->restore(self::membership($record));
                    DomainNotifications::success(__('purchase_supplier.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $record->unsetRelation('purchaseSupplier');
            });
    }

    public static function membership(Party $record): ?PurchaseSupplier
    {
        $membership = $record->relationLoaded('purchaseSupplier')
            ? $record->getRelation('purchaseSupplier')
            : $record->purchaseSupplier()->first();

        return $membership instanceof PurchaseSupplier ? $membership : null;
    }
}
