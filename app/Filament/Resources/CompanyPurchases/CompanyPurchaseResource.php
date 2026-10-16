<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyPurchases;

use App\Filament\NavigationGroup;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\CompanyPurchases\Pages\CreateCompanyPurchase;
use App\Filament\Resources\CompanyPurchases\Pages\EditCompanyPurchase;
use App\Filament\Resources\CompanyPurchases\Pages\ListCompanyPurchases;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\RecordLinks;
use App\Filament\Support\RowDetail;
use App\Models\Party\Party;
use App\Models\Procurement\CompanyPurchase;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Procurement\CompanyPurchaseQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CompanyPurchaseService;
use App\Support\DisplayTime;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Satin Alma > Sirket ici alimlar (B52, D-189). Kullanicinin istegi (10 Ekim
 * 2026): "Zaten alınmış olanların da kaydı olması gerek. Bazı hizmetlerin
 * ürünlerin yenilenme dönemleri için tarih olacak (sunucu - claude - kalem -
 * defter); oluşacak giderler hesaplanabilecek." Liste basliginda onumuzdeki
 * 12 ayin yenileme gideri ve yaklasan yenileme sayisi yazar. Silme yok,
 * arsiv var (D-156).
 *
 * D-192 (B53; kullanici: "yine sirket ici alimlar kismi da ayni tarz bir
 * arayuz"): "Alım ekle" kalemlidir (CompanyPurchaseLinesForm); listede alimin
 * talebi (SAT) ve siparisi (SIP) baglantili sutunlardir.
 */
class CompanyPurchaseResource extends Resource
{
    protected static ?string $model = CompanyPurchase::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    protected static ?int $navigationSort = 5;

    // Baslik buyuk harfe cevrilince "icI" Turkce bozuluyordu ("Sirket Ici Alimlar"); ad yazildigi gibi kalir.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('company_purchase.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('company_purchase.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('company_purchase.navigation');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::CompanyPurchases)
            && SchemaReadiness::hasBatch('B52A')
            && parent::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return app(CompanyPurchaseQueries::class)->withRequestLinks(
            app(PurchasingQueries::class)->withCompanyPurchaseRelations(parent::getEloquentQuery()),
        );
    }

    /**
     * D-192 (B53): "Alım ekle" kalemlidir (CompanyPurchaseLinesForm; bir
     * talebin kalemleri tek ekranda, her kalem ayri alim satiri). Duzenle ve
     * B53 oncesi ekle tek kayitli formdur.
     */
    public static function itemized(Schema $schema): bool
    {
        return $schema->getOperation() === 'create' && SchemaReadiness::hasBatch('B53');
    }

    public static function form(Schema $schema): Schema
    {
        if (self::itemized($schema)) {
            return $schema->columns(1)->components(CompanyPurchaseLinesForm::components());
        }

        return $schema->columns(1)->components([
            Section::make(__('company_purchase.sections.purchase'))
                ->icon(Heroicon::OutlinedComputerDesktop)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    // D-190: alim bir satin alma talebine baglidir; secilince alanlar talepten dolar.
                    Select::make('purchase_need_id')
                        ->label(__('company_purchase.fields.request'))
                        ->helperText(__('company_purchase.help.request'))
                        ->options(fn (): array => app(CompanyPurchaseQueries::class)->requestOptions())
                        ->getSearchResultsUsing(fn (string $search): array => app(CompanyPurchaseQueries::class)->requestOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => filled($value) ? (app(CompanyPurchaseQueries::class)->requestOptions('', (int) $value)[(int) $value] ?? null) : null)
                        ->searchable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            foreach (app(CompanyPurchaseQueries::class)->prefillFromRequest((int) $state) as $field => $value) {
                                $set($field, $field === 'unit_price' && $value !== null ? Money::fixed($value, 4) : $value);
                            }
                        })
                        ->native(false)
                        ->columnSpan(FieldGrid::WIDE),
                    Select::make('catalog_item_id')
                        ->label(__('company_purchase.fields.catalog_item'))
                        ->helperText(__('company_purchase.help.catalog_item'))
                        ->options(fn (): array => app(PurchasingQueries::class)->purchasableItemOptions())
                        ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->purchasableItemOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->itemLabel($value))
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->columnSpan(FieldGrid::WIDE),
                    TextInput::make('description')
                        ->label(__('company_purchase.fields.description'))
                        ->maxLength(300),
                    Select::make('org_unit_id')
                        ->label(__('company_purchase.fields.org_unit'))
                        ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                        ->searchable()
                        ->native(false),
                    Select::make('personnel_id')
                        ->label(__('company_purchase.fields.personnel'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->searchable()
                        ->native(false),
                    Select::make('supplier_party_id')
                        ->label(__('company_purchase.fields.supplier_party'))
                        ->options(fn (): array => app(PurchasingQueries::class)->supplierOptions())
                        ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->supplierOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->supplierLabel($value))
                        ->searchable()
                        ->live()
                        ->native(false),
                    TextInput::make('supplier_name')
                        ->label(__('company_purchase.fields.supplier_name'))
                        ->helperText(__('company_purchase.help.supplier_name'))
                        ->maxLength(200)
                        ->visible(fn (Get $get): bool => blank($get('supplier_party_id'))),
                ])),
            Section::make(__('company_purchase.sections.amount'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    DatePicker::make('purchased_on')
                        ->label(__('company_purchase.fields.purchased_on'))
                        ->displayFormat('d.m.Y')
                        ->default(fn (): string => Carbon::now(DisplayTime::zone())->toDateString())
                        ->required(),
                    TextInput::make('quantity')
                        ->label(__('company_purchase.fields.quantity'))
                        ->numeric()
                        ->minValue(0.0001)
                        ->default(1)
                        ->required(),
                    MoneyInput::make('unit_price')
                        ->label(__('company_purchase.fields.unit_price'))
                        ->decimals(4)
                        ->required(),
                    Select::make('currency_code')
                        ->label(__('company_purchase.fields.currency'))
                        ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                        ->default('TRY')
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('renewal_months')
                        ->label(__('company_purchase.fields.renewal_months'))
                        ->helperText(__('company_purchase.help.renewal_months'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(120)
                        ->suffix(__('company_purchase.values.months')),
                    DatePicker::make('next_renewal_on')
                        ->label(__('company_purchase.fields.next_renewal_on'))
                        ->helperText(__('company_purchase.help.next_renewal_on'))
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('ends_on')
                        ->label(__('company_purchase.fields.ends_on'))
                        ->displayFormat('d.m.Y'),
                    Textarea::make('note')
                        ->label(__('company_purchase.fields.note'))
                        ->rows(3)
                        ->maxLength(4000),
                    Hidden::make('row_version'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(fn (): string => self::outlook())
            ->columns([
                TextColumn::make('catalogItem.name')
                    ->label(__('company_purchase.fields.catalog_item'))
                    ->icon(fn (CompanyPurchase $record): ?Heroicon => $record->catalogItem?->kindEnum()->getIcon())
                    ->iconColor(fn (CompanyPurchase $record): ?string => $record->catalogItem?->kindEnum()->getColor())
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('company_purchase.fields.description'))
                    ->limit(40)
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('orgUnit.name')
                    ->label(__('company_purchase.fields.org_unit'))
                    ->placeholder('-'),
                TextColumn::make('personnel.full_name')
                    ->label(__('company_purchase.fields.personnel'))
                    ->placeholder('-'),
                TextColumn::make('supplier_label')
                    ->label(__('company_purchase.fields.supplier'))
                    ->state(fn (CompanyPurchase $record): ?string => self::supplierLabel($record))
                    ->url(fn (CompanyPurchase $record): ?string => $record->supplier instanceof Party ? RecordLinks::detailUrl($record->supplier, checkRecord: false) : null)
                    ->icon(fn (CompanyPurchase $record): mixed => $record->supplier instanceof Party ? RecordLinks::iconFor($record->supplier) : null)
                    ->limit(30)
                    ->placeholder('-'),
                TextColumn::make('purchased_on')
                    ->label(__('company_purchase.fields.purchased_on'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('company_purchase.fields.quantity'))
                    ->formatStateUsing(fn (mixed $state): string => Money::number($state, 2) ?? '-')
                    ->alignEnd(),
                TextColumn::make('unit_price')
                    ->label(__('company_purchase.fields.unit_price'))
                    ->formatStateUsing(fn (mixed $state, CompanyPurchase $record): string => Money::format($state, $record->getAttribute('currency_code'), '-', 4))
                    ->alignEnd(),
                TextColumn::make('total')
                    ->label(__('company_purchase.fields.total'))
                    ->state(fn (CompanyPurchase $record): float => $record->totalAmount())
                    ->formatStateUsing(fn (mixed $state, CompanyPurchase $record): string => Money::format($state, $record->getAttribute('currency_code')))
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('renewal')
                    ->label(__('company_purchase.fields.renewal'))
                    ->state(fn (CompanyPurchase $record): string => self::renewalText($record))
                    ->badge()
                    ->color(fn (CompanyPurchase $record): string => $record->getAttribute('renewal_months') !== null ? 'info' : 'gray'),
                TextColumn::make('next_renewal_on')
                    ->label(__('company_purchase.fields.next_renewal_on'))
                    ->date('d.m.Y')
                    ->color(fn (CompanyPurchase $record): ?string => self::renewalTone($record))
                    ->icon(fn (CompanyPurchase $record): ?Heroicon => self::renewalTone($record) !== null ? Heroicon::OutlinedClock : null)
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('need.title')
                    ->label(__('company_purchase.fields.need'))
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(),
                // D-192 (B53): kalemli alimin talebi (SAT) ve geldigi siparis (SIP).
                TextColumn::make('purchaseRequest.request_no')
                    ->label(__('company_purchase.fields.purchase_request'))
                    ->placeholder('-')
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B53')),
                TextColumn::make('purchaseOrder.order_no')
                    ->label(__('company_purchase.fields.purchase_order'))
                    ->placeholder('-')
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B53')),
            ])
            ->filters([
                // Arsiv (D-156): varsayilan yalniz aktif kayitlar.
                SelectFilter::make('archive')
                    ->label(__('company_purchase.filters.archive'))
                    ->options([
                        'active' => __('company_purchase.filters.archive_active'),
                        'archived' => __('company_purchase.filters.archive_archived'),
                        'all' => __('company_purchase.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(CompanyPurchaseQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
                SelectFilter::make('org_unit_id')
                    ->label(__('company_purchase.fields.org_unit'))
                    ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                    ->searchable()
                    ->native(false),
            ])
            ->recordActions([
                RowDetail::action(),
                EditAction::make()
                    ->visible(fn (CompanyPurchase $record): bool => Gate::allows('update', $record)),
                self::renewAction(),
                self::archiveAction(),
                self::restoreAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('company_purchase.empty'))
            ->emptyStateIcon(Heroicon::OutlinedComputerDesktop)
            ->defaultSort('purchased_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanyPurchases::route('/'),
            'create' => CreateCompanyPurchase::route('/create'),
            'edit' => EditCompanyPurchase::route('/{record}/edit'),
        ];
    }

    /** "Önümüzdeki 12 ay yenileme gideri: ... · N yenileme yaklaşıyor". */
    public static function outlook(): string
    {
        $queries = app(CompanyPurchaseQueries::class);

        return __('company_purchase.outlook', [
            'amount' => Money::sums($queries->projectedRenewals(12), Money::format(0, 'TRY')),
            'count' => $queries->upcomingCount(),
        ]);
    }

    private static function renewAction(): Action
    {
        return Action::make('renew')
            ->label(__('company_purchase.actions.renew'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color(ActionColors::SAVE)
            ->visible(fn (CompanyPurchase $record): bool => ! $record->isArchived()
                && $record->getAttribute('renewal_months') !== null
                && $record->getAttribute('next_renewal_on') !== null
                && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalHeading(__('company_purchase.actions.renew'))
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalDescription(fn (CompanyPurchase $record): string => __('company_purchase.help.renew', [
                'current' => $record->getAttribute('next_renewal_on')?->format('d.m.Y') ?? '-',
                'next' => $record->getAttribute('next_renewal_on')?->copy()->addMonthsNoOverflow((int) $record->getAttribute('renewal_months'))->format('d.m.Y') ?? '-',
            ]))
            ->modalSubmitActionLabel(__('company_purchase.actions.renew'))
            ->action(function (CompanyPurchase $record): void {
                try {
                    app(CompanyPurchaseService::class)->renew($record);
                    DomainNotifications::success(__('company_purchase.messages.renewed'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    private static function archiveAction(): Action
    {
        return Action::make('archive_purchase')
            ->label(__('company_purchase.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->visible(fn (CompanyPurchase $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('company_purchase.actions.archive'))
            ->modalDescription(__('company_purchase.help.archive'))
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->modalSubmitActionLabel(__('company_purchase.actions.archive'))
            ->action(function (CompanyPurchase $record): void {
                try {
                    app(CompanyPurchaseService::class)->archive($record);
                    DomainNotifications::success(__('company_purchase.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    private static function restoreAction(): Action
    {
        return Action::make('restore_purchase')
            ->label(__('company_purchase.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (CompanyPurchase $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('company_purchase.actions.restore'))
            ->modalDescription(__('company_purchase.help.restore'))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel(__('company_purchase.actions.restore'))
            ->action(function (CompanyPurchase $record): void {
                try {
                    app(CompanyPurchaseService::class)->restore($record);
                    DomainNotifications::success(__('company_purchase.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    private static function supplierLabel(CompanyPurchase $record): ?string
    {
        $party = $record->supplier;

        if ($party instanceof Party) {
            return (string) $party->getAttribute('display_name');
        }

        $name = $record->getAttribute('supplier_name');

        return filled($name) ? (string) $name : null;
    }

    /** "12 ayda bir" ya da "Tek seferlik". */
    private static function renewalText(CompanyPurchase $record): string
    {
        $months = $record->getAttribute('renewal_months');

        return $months !== null
            ? __('company_purchase.values.every_months', ['months' => (int) $months])
            : __('company_purchase.values.one_time');
    }

    /** Yenileme tarihi gecmis kirmizi, 60 gun icinde turuncu. */
    private static function renewalTone(CompanyPurchase $record): ?string
    {
        $next = $record->getAttribute('next_renewal_on');

        if ($next === null || $record->getAttribute('renewal_months') === null) {
            return null;
        }

        $ends = $record->getAttribute('ends_on');
        $today = Carbon::now(DisplayTime::zone())->toDateString();

        if ($ends !== null && $ends->toDateString() < $today) {
            return null;
        }

        $date = $next->toDateString();

        return match (true) {
            $date < $today => 'danger',
            $date <= Carbon::parse($today)->addDays(CompanyPurchaseQueries::UPCOMING_DAYS)->toDateString() => 'warning',
            default => null,
        };
    }
}
