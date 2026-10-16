<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Support\RecordLinks;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Project\Project;
use App\Query\Project\ProjectPurchasingQueries;
use App\Support\Projects\ProjectPurchasing;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

/**
 * Proje sayfasinda Lojistik adiminin "Lojistik" tablosu (D-196; eski
 * "Sevkiyat ve teslimat" tablosunun yerine, anahtar ProjectPurchasing ve
 * Siparisler ozelligi): projenin siparisleri - kod, tedarikci, lojistik
 * asamasi, beklenen teslim (gecikmisse kirmizi), tasiyici, takip no, teslim
 * adresi. Salt okunur; siparis yalniz satin alma teklifinden acilir ve
 * lojistigi siparis sayfasinda ilerler. Satira tiklamak siparis sayfasini
 * acar (TableConventions, PurchaseOrderPolicy).
 */
class ProjectPurchaseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedTruck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_supply_item.purchasing.orders_title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ProjectPurchasing::ordersEnabled() && Gate::allows('viewAny', PurchaseOrder::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /** Projeye yazilmis ve projenin kalemini tasiyan siparisler (iliski; RelationManager Builder kabul etmez). */
    public function getRelationship(): Relation | Builder
    {
        /** @var Project $project */
        $project = $this->getOwnerRecord();

        return app(ProjectPurchasingQueries::class)->ordersRelation($project);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('project_supply_item.purchasing.orders_title'))
            ->modelLabel(__('purchase_order.label'))
            ->pluralModelLabel(__('purchase_order.plural'))
            ->recordTitleAttribute('order_no')
            ->modifyQueryUsing(fn (Builder $query): Builder => app(ProjectPurchasingQueries::class)->ordersTable($query, $this->viewer()))
            ->columns([
                TextColumn::make('order_no')
                    ->label(__('purchase_order.fields.order_no'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('supplier_label')
                    ->label(__('purchase_order.fields.supplier'))
                    ->state(fn (PurchaseOrder $record): ?string => $record->supplierLabel() !== '' ? $record->supplierLabel() : null)
                    ->url(fn (PurchaseOrder $record): ?string => $record->supplier instanceof Party ? RecordLinks::detailUrl($record->supplier, checkRecord: false) : null)
                    ->icon(fn (PurchaseOrder $record): mixed => $record->supplier instanceof Party ? RecordLinks::iconFor($record->supplier) : null)
                    ->placeholder(__('purchase_order.values.no_supplier'))
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('purchase_order.fields.status'))
                    ->badge(),
                // Beklenen teslim; girilmemisse teslim doneminin sonu (PurchaseOrder::isLate ile ayni).
                TextColumn::make('expected_delivery_on')
                    ->label(__('purchase_order.fields.expected_delivery_on'))
                    ->state(fn (PurchaseOrder $record): mixed => $record->getAttribute('expected_delivery_on') ?? $record->getAttribute('delivery_until'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseOrder $record): ?string => $record->isLate() ? 'danger' : null)
                    ->icon(fn (PurchaseOrder $record): ?Heroicon => $record->isLate() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->tooltip(fn (PurchaseOrder $record): ?string => $record->isLate() ? __('purchase_order.values.late') : null)
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('carrier')
                    ->label(__('purchase_order.fields.carrier'))
                    ->placeholder('-')
                    ->limit(30),
                TextColumn::make('tracking_no')
                    ->label(__('purchase_order.fields.tracking_no'))
                    ->placeholder('-')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('delivery_address')
                    ->label(__('purchase_order.fields.delivery_address'))
                    ->placeholder('-')
                    ->wrap()
                    ->limit(60),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('purchase_order.fields.status'))
                    ->options(PurchaseOrderStatus::class)
                    ->native(false),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('project_supply_item.purchasing.orders_empty'))
            ->emptyStateIcon(Heroicon::OutlinedTruck)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    private function viewer(): ?Personnel
    {
        $user = auth()->user();

        return $user instanceof Personnel ? $user : null;
    }
}
