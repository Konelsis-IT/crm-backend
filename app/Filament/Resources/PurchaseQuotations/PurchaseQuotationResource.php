<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations;

use App\Enums\Platform\Feature;
use App\Filament\NavigationGroup;
use App\Filament\Resources\PurchaseQuotations\Pages\CreatePurchaseQuotation;
use App\Filament\Resources\PurchaseQuotations\Pages\EditPurchaseQuotation;
use App\Filament\Resources\PurchaseQuotations\Pages\ListPurchaseQuotations;
use App\Filament\Resources\PurchaseQuotations\Pages\ViewPurchaseQuotation;
use App\Filament\Resources\PurchaseQuotations\RelationManagers\DocumentsRelationManager;
use App\Filament\Support\CodeLine;
use App\Filament\Support\RecordLinks;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseQuotation;
use App\Query\Procurement\PurchaseQuotationQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Operasyon - Satin alma > Satin alma teklifleri (D-192, B53). Kullanicinin
 * istegi (12 Ekim 2026): "daha kapsamlisi 'Satin alma teklifleri' kismi
 * olusturulup orada tutulmali ... topluca teklif'te birlestirilip ortak bir
 * siparise donusturulebilmesi". Tedarikci teklifleri kalemli tutulur
 * (miktar, birim fiyat, indirim, opsiyon / iptal), belgeler eklenir, teklifler
 * karsilastirilir, en uygunu secilir ve siparise donusturulur; standart PDF.
 *
 * Ekran yalniz B53 uygulandiginda ve Feature::PurchaseQuotations acikken
 * gorunur. Silme yok, arsiv var (D-156); Arsiv suzgeci varsayilan Aktif.
 */
class PurchaseQuotationResource extends Resource
{
    protected static ?string $model = PurchaseQuotation::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'quotation_no';

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('purchase_quotation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_quotation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_quotation.navigation');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::PurchaseQuotations)
            && SchemaReadiness::hasBatch('B53')
            && parent::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return app(PurchaseQuotationQueries::class)->withListRelations(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(PurchaseQuotationForm::components());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(PurchaseQuotationInfolist::components());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Teklif no (STKF) ayri sutun degil, tedarikcinin altinda kucuk yazi;
                // tedarikcinin teklif referansi yaninda (CodeLine, 14 Ekim 2026).
                // Arama teklif nosundan yapilir.
                CodeLine::under(
                    TextColumn::make('supplier_label')
                        ->label(__('purchase_quotation.fields.supplier'))
                        ->state(fn (PurchaseQuotation $record): ?string => $record->supplierLabel() !== '' ? $record->supplierLabel() : null)
                        ->url(fn (PurchaseQuotation $record): ?string => $record->supplier instanceof Party ? RecordLinks::detailUrl($record->supplier, checkRecord: false) : null)
                        ->icon(fn (PurchaseQuotation $record): mixed => $record->supplier instanceof Party ? RecordLinks::iconFor($record->supplier) : null)
                        ->placeholder(__('purchase_quotation.values.no_supplier'))
                        ->limit(40)
                        ->searchable(['quotation_no']),
                    'quotation_no',
                    fn (PurchaseQuotation $record): ?string => filled($record->getAttribute('supplier_reference')) ? (string) $record->getAttribute('supplier_reference') : null,
                ),
                TextColumn::make('quote_date')
                    ->label(__('purchase_quotation.fields.quote_date'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('purchase_quotation.fields.valid_until'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseQuotation $record): ?string => $record->isExpired() ? 'danger' : null)
                    ->icon(fn (PurchaseQuotation $record): ?Heroicon => $record->isExpired() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->tooltip(fn (PurchaseQuotation $record): ?string => $record->isExpired() ? __('purchase_quotation.values.expired') : null)
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('line_count')
                    ->label(__('purchase_quotation.fields.line_count'))
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('total_amount')
                    ->label(__('purchase_quotation.fields.total'))
                    ->formatStateUsing(fn (mixed $state, PurchaseQuotation $record): string => Money::format($state, $record->getAttribute('currency_code')))
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total_try')
                    ->label(__('purchase_quotation.fields.total_try'))
                    ->state(fn (PurchaseQuotation $record): ?float => $record->totalInTry())
                    ->formatStateUsing(fn (mixed $state): string => Money::format($state, 'TRY'))
                    ->placeholder('-')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('purchase_quotation.fields.status'))
                    ->badge(),
                TextColumn::make('ai_status')
                    ->label(__('purchase_quotation.fields.ai_status'))
                    ->badge()
                    ->placeholder('')
                    ->toggleable(),
                TextColumn::make('order.order_no')
                    ->label(__('purchase_quotation.fields.order'))
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                // Arsiv (D-156): varsayilan yalniz aktif teklifler.
                SelectFilter::make('archive')
                    ->label(__('purchase_quotation.filters.archive'))
                    ->options([
                        'active' => __('purchase_quotation.filters.archive_active'),
                        'archived' => __('purchase_quotation.filters.archive_archived'),
                        'all' => __('purchase_quotation.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(PurchaseQuotationQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (PurchaseQuotation $record): bool => Gate::allows('update', $record)),
                PurchaseQuotationActions::archive(),
                PurchaseQuotationActions::restore(),
            ])
            ->toolbarActions([
                PurchaseQuotationActions::compareBulk(),
            ])
            ->emptyStateHeading(__('purchase_quotation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentCurrencyDollar)
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseQuotations::route('/'),
            'create' => CreatePurchaseQuotation::route('/create'),
            'view' => ViewPurchaseQuotation::route('/{record}'),
            'edit' => EditPurchaseQuotation::route('/{record}/edit'),
        ];
    }
}
