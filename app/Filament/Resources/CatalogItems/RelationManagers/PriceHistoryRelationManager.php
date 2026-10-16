<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\RelationManagers;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Support\CodeLine;
use App\Filament\Support\MoneyDisplay;
use App\Filament\Support\PurchaseLines;
use App\Filament\Support\RecordLinks;
use App\Models\Party\Party;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Query\Procurement\CatalogScreenQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * Malzeme / hizmet sayfasinda "Fiyat gecmisi" (D-194, B53; kullanici 12 Ekim
 * 2026: "fiyat gecmisi olacakta; kaldirilan alani gostermek gereksiz"). Eski
 * "Siparisler" sekmesinin (B52 kalem basina tedarikci teklif kartlari) yerini
 * alir.
 *
 * Bu kalemin butun satin alma tekliflerindeki fiyatli satirlari, en yeni
 * teklif basta: teklif tarihi, tedarikci (altinda teklif no), miktar
 * ve birim, birim fiyat, TL karsiligi, indirim, teklif durumu (Secildi /
 * Siparis verildi renkli), siparis no. Ustte ozet: en dusuk, son ve ortalama
 * TL birim fiyat. Suzgec: tedarikci, teklif durumu. Salt okunur; satira
 * tiklamak teklifi acar.
 */
class PriceHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'quotationLines';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedChartBar;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('catalog.relations.price_history.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return CatalogItemResource::enabled()
            && SchemaReadiness::hasBatch('B53')
            && FeatureFlags::enabled(Feature::PurchaseQuotations)
            && Gate::allows('viewAny', PurchaseQuotation::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /** Iliski (Relation) doner: Filament sorguyu getQuery() ile alir. */
    public function getRelationship(): Relation | Builder
    {
        /** @var CatalogItem $item */
        $item = $this->getOwnerRecord();

        return app(CatalogScreenQueries::class)->priceHistory($item);
    }

    public function table(Table $table): Table
    {
        /** @var CatalogItem $item */
        $item = $this->getOwnerRecord();
        $highlight = static fn (PurchaseQuotationLine $record): bool => in_array(
            $record->quotation?->statusEnum(),
            [PurchaseQuotationStatus::Selected, PurchaseQuotationStatus::Ordered],
            true,
        );

        return $table
            ->heading(__('catalog.relations.price_history.title'))
            ->description(fn (): ?HtmlString => self::summary($item))
            // Satira tiklamak teklifi acar (satirin kendi sayfasi yok).
            ->recordUrl(fn (PurchaseQuotationLine $record): ?string => $record->quotation instanceof PurchaseQuotation ? RecordLinks::detailUrl($record->quotation) : null)
            ->columns([
                TextColumn::make('quote_date')
                    ->label(__('catalog.relations.price_history.quote_date'))
                    ->state(fn (PurchaseQuotationLine $record): mixed => $record->quotation?->getAttribute('quote_date'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                // Teklif no (STKF) ayri sutun degil, tedarikcinin altinda kucuk yazi
                // (CodeLine, 14 Ekim 2026).
                CodeLine::under(
                    TextColumn::make('supplier')
                        ->label(__('catalog.relations.price_history.supplier'))
                        ->state(fn (PurchaseQuotationLine $record): ?string => $record->quotation?->supplierLabel() ?: null)
                        ->url(fn (PurchaseQuotationLine $record): ?string => ($party = $record->quotation?->supplier) instanceof Party ? RecordLinks::detailUrl($party, checkRecord: false) : null)
                        ->icon(fn (PurchaseQuotationLine $record): mixed => ($party = $record->quotation?->supplier) instanceof Party ? RecordLinks::iconFor($party) : null)
                        ->placeholder('-')
                        ->wrap(),
                    'quotation.quotation_no',
                ),
                TextColumn::make('quantity')
                    ->label(__('catalog.relations.price_history.quantity'))
                    ->formatStateUsing(fn (mixed $state, PurchaseQuotationLine $record): string => PurchaseLines::quantityText($state, app(UnitResolver::class)->display($record)))
                    ->alignEnd(),
                MoneyDisplay::column('unit_price', fn (?Model $record): mixed => $record instanceof PurchaseQuotationLine ? $record->quotation?->getAttribute('currency_code') : null, 4)
                    ->label(__('catalog.relations.price_history.unit_price'))
                    ->weight(fn (PurchaseQuotationLine $record): ?FontWeight => $highlight($record) ? FontWeight::Bold : null)
                    ->description(fn (PurchaseQuotationLine $record): ?string => match (true) {
                        (bool) $record->getAttribute('is_cancelled') => __('catalog.relations.price_history.badges.cancelled'),
                        (bool) $record->getAttribute('is_option') => __('catalog.relations.price_history.badges.option'),
                        default => null,
                    }),
                TextColumn::make('unit_price_try')
                    ->label(__('catalog.relations.price_history.unit_price_try'))
                    ->tooltip(__('catalog.relations.price_history.unit_price_try_help'))
                    ->state(fn (PurchaseQuotationLine $record): ?float => CatalogScreenQueries::lineUnitInTry($record))
                    ->formatStateUsing(fn (mixed $state): string => Money::format($state, 'TRY', '-', 2))
                    ->placeholder('-')
                    ->alignEnd(),
                TextColumn::make('discount_rate')
                    ->label(__('catalog.relations.price_history.discount'))
                    ->formatStateUsing(fn (mixed $state): string => (float) $state > 0 ? '%'.Money::number($state, 2) : '-')
                    ->alignEnd(),
                TextColumn::make('quotation_status')
                    ->label(__('catalog.relations.price_history.status'))
                    ->state(fn (PurchaseQuotationLine $record): ?PurchaseQuotationStatus => $record->quotation?->statusEnum())
                    ->badge(),
                TextColumn::make('orderLine.order.order_no')
                    ->label(__('catalog.relations.price_history.order'))
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('supplier')
                    ->label(__('catalog.relations.price_history.filters.supplier'))
                    ->options(fn (): array => app(CatalogScreenQueries::class)->priceHistorySuppliers($item))
                    ->searchable()
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(CatalogScreenQueries::class)->priceHistorySupplierScope($query, $data['value'] ?? null)),
                SelectFilter::make('quotation_status')
                    ->label(__('catalog.relations.price_history.filters.status'))
                    ->options(PurchaseQuotationStatus::class)
                    ->multiple()
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(CatalogScreenQueries::class)->priceHistoryStatusScope($query, (array) ($data['values'] ?? []))),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('catalog.relations.price_history.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChartBar)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * Ozet rozetleri: en dusuk (yesil), son, ortalama TL birim fiyat ve fiyat
     * sayisi (onayli rozet HTML'i, PurchaseLines; metinler kacirilir).
     */
    private static function summary(CatalogItem $item): ?HtmlString
    {
        $summary = app(CatalogScreenQueries::class)->priceSummary($item);

        if ($summary['count'] === 0 && $summary['missing_rate'] === 0) {
            return null;
        }

        $badges = [];

        if ($summary['count'] > 0) {
            $badges[] = [__('catalog.relations.price_history.summary.lowest', ['price' => Money::format($summary['lowest'], 'TRY')]), PurchaseLines::TONE_GREEN];
            $badges[] = [__('catalog.relations.price_history.summary.last', ['price' => Money::format($summary['last'], 'TRY')]), PurchaseLines::TONE_BLUE];
            $badges[] = [__('catalog.relations.price_history.summary.average', ['price' => Money::format($summary['average'], 'TRY')]), PurchaseLines::TONE_GRAY];
            $badges[] = [__('catalog.relations.price_history.summary.count', ['count' => $summary['count']]), PurchaseLines::TONE_GRAY];
        }

        if ($summary['missing_rate'] > 0) {
            $badges[] = [__('catalog.relations.price_history.summary.no_rate'), PurchaseLines::TONE_AMBER];
        }

        return PurchaseLines::badges($badges);
    }
}
