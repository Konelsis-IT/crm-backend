<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders;

use App\Enums\Platform\Feature;
use App\Filament\NavigationGroup;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Support\CodeLine;
use App\Filament\Support\RecordLinks;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseOrder;
use App\Query\Procurement\PurchaseOrderQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Operasyon - Satin alma > Siparisler (D-192, B53). Kullanicinin istegi
 * (12 Ekim 2026): "Siparisler ise ilgili teklif onaylandiginda olusan surec
 * icin kullanilan bir yapidir. Tekliften gelir. Secili teklif siparise
 * donusur. Ayni zamanda logistic sureci de olmasi lazim. Teslim edilecegi
 * donem ... teslim edilecegi adres".
 *
 * Olusturma sayfasi yoktur: siparis yalniz secili satin alma teklifinden
 * ("Siparişe dönüştür") acilir. Onay bildirimi siparis sayfasini acar
 * (PurchaseOrderSubject `view` sayfasini kullanir). Ekran yalniz B53
 * uygulandiginda ve Feature::PurchaseOrders acikken gorunur. Silme yok,
 * arsiv var (D-156).
 */
class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'order_no';

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('purchase_order.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_order.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_order.navigation');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::PurchaseOrders)
            && SchemaReadiness::hasBatch('B53')
            && parent::canAccess();
    }

    /** Siparis yalniz tekliften acilir; olusturma sayfasi ve dugmesi yok. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Kisi yalniz gorebildigi siparisleri gorur (satin alma ve ust yonetim hepsini). */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return app(PurchaseOrderQueries::class)->withListRelations(
            app(PurchaseOrderQueries::class)->visibleTo(parent::getEloquentQuery(), $user instanceof Personnel ? $user : null),
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(PurchaseOrderInfolist::components());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Siparis no (SIP) ayri sutun degil, tedarikcinin altinda kucuk yazi
                // (CodeLine, 14 Ekim 2026); arama siparis nosundan yapilir.
                CodeLine::under(
                    TextColumn::make('supplier_label')
                        ->label(__('purchase_order.fields.supplier'))
                        ->state(fn (PurchaseOrder $record): ?string => $record->supplierLabel() !== '' ? $record->supplierLabel() : null)
                        ->url(fn (PurchaseOrder $record): ?string => $record->supplier instanceof Party ? RecordLinks::detailUrl($record->supplier, checkRecord: false) : null)
                        ->icon(fn (PurchaseOrder $record): mixed => $record->supplier instanceof Party ? RecordLinks::iconFor($record->supplier) : null)
                        ->placeholder(__('purchase_order.values.no_supplier'))
                        ->limit(40)
                        ->searchable(['order_no']),
                    'order_no',
                ),
                TextColumn::make('quotation.quotation_no')
                    ->label(__('purchase_order.fields.quotation'))
                    ->placeholder('-'),
                TextColumn::make('project.name')
                    ->label(__('purchase_order.fields.project'))
                    ->formatStateUsing(fn (mixed $state, PurchaseOrder $record): string => (string) ($record->project?->getAttribute('display_name') ?? $state))
                    ->limit(30)
                    ->placeholder('-'),
                TextColumn::make('line_count')
                    ->label(__('purchase_order.fields.line_count'))
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('total_amount')
                    ->label(__('purchase_order.fields.total'))
                    ->formatStateUsing(fn (mixed $state, PurchaseOrder $record): string => Money::format($state, $record->getAttribute('currency_code')))
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total_try')
                    ->label(__('purchase_order.fields.total_try'))
                    ->state(fn (PurchaseOrder $record): ?float => $record->totalInTry())
                    ->formatStateUsing(fn (mixed $state): string => Money::format($state, 'TRY'))
                    ->placeholder('-')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('purchase_order.fields.status'))
                    ->badge(),
                TextColumn::make('expected_delivery_on')
                    ->label(__('purchase_order.fields.expected_delivery_on'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseOrder $record): ?string => $record->isLate() ? 'danger' : null)
                    ->icon(fn (PurchaseOrder $record): ?Heroicon => $record->isLate() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->tooltip(fn (PurchaseOrder $record): ?string => $record->isLate() ? __('purchase_order.values.late') : null)
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('ordered_at')
                    ->label(__('purchase_order.fields.ordered_at'))
                    ->dateTime('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                // Arsiv (D-156): varsayilan yalniz aktif siparisler.
                SelectFilter::make('archive')
                    ->label(__('purchase_order.filters.archive'))
                    ->options([
                        'active' => __('purchase_order.filters.archive_active'),
                        'archived' => __('purchase_order.filters.archive_archived'),
                        'all' => __('purchase_order.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(PurchaseOrderQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
            ])
            ->recordActions([
                PurchaseOrderActions::archive(),
                PurchaseOrderActions::restore(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('purchase_order.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart)
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
