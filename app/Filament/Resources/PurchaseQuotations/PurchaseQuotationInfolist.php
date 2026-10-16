<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations;

use App\Filament\Support\PurchaseLines;
use App\Filament\Support\QuoteComparison;
use App\Filament\Support\RecordLinks;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Query\Procurement\PurchaseQuotationQueries;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

/**
 * Satin alma teklifi ayrintisi (D-192): yan yana teklif bilgileri ve fiyat
 * bilgileri (formdaki yarim kartlarla ayni genislik, D-168), kalem tablosu
 * (formdaki rozetlerle) ve "Karsilastirma" (bu teklifteki her kalemin diger
 * tekliflerdeki fiyatlari, kartlarla). Belgeler alt listede
 * (RelationManagers\DocumentsRelationManager).
 */
final class PurchaseQuotationInfolist
{
    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            // Konelsis AI okumasi surerken ilerleme ve sonuc yoklamasi (yalniz okuma bekleniyorsa gorunur).
            PurchaseQuotationAiActions::progress(),
            Grid::make(['default' => 1, 'xl' => 2])->components([
                self::headerSection(),
                self::pricesSection(),
            ]),
            self::linesSection(),
            Section::make(__('purchase_quotation.sections.comparison'))
                ->icon(Heroicon::OutlinedScale)
                ->collapsible()
                ->schema(fn (PurchaseQuotation $record): array => QuoteComparison::quotationOffers(app(PurchaseQuotationQueries::class)->relatedOffers($record))),
        ];
    }

    private static function headerSection(): Section
    {
        return Section::make(__('purchase_quotation.sections.header'))
            ->icon(Heroicon::OutlinedBuildingStorefront)
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextEntry::make('quotation_no')
                    ->label(__('purchase_quotation.fields.quotation_no'))
                    ->weight(FontWeight::Bold),
                TextEntry::make('status')
                    ->label(__('purchase_quotation.fields.status'))
                    ->badge(),
                TextEntry::make('supplier_label')
                    ->label(__('purchase_quotation.fields.supplier'))
                    ->state(fn (PurchaseQuotation $record): ?string => $record->supplierLabel() !== '' ? $record->supplierLabel() : null)
                    ->url(fn (PurchaseQuotation $record): ?string => $record->supplier instanceof Party ? RecordLinks::detailUrl($record->supplier, checkRecord: false) : null)
                    ->icon(fn (PurchaseQuotation $record): mixed => $record->supplier instanceof Party ? RecordLinks::iconFor($record->supplier) : null)
                    ->placeholder(__('purchase_quotation.values.no_supplier')),
                TextEntry::make('supplier_reference')
                    ->label(__('purchase_quotation.fields.supplier_reference'))
                    ->placeholder('-'),
                TextEntry::make('quote_date')
                    ->label(__('purchase_quotation.fields.quote_date'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextEntry::make('valid_until')
                    ->label(__('purchase_quotation.fields.valid_until'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseQuotation $record): ?string => $record->isExpired() ? 'danger' : null)
                    ->icon(fn (PurchaseQuotation $record): ?Heroicon => $record->isExpired() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->tooltip(fn (PurchaseQuotation $record): ?string => $record->isExpired() ? __('purchase_quotation.values.expired') : null)
                    ->placeholder('-'),
                TextEntry::make('currency_code')
                    ->label(__('purchase_quotation.fields.currency'))
                    ->formatStateUsing(fn (mixed $state): string => Money::label($state)),
                TextEntry::make('try_rate')
                    ->label(__('purchase_quotation.fields.try_rate'))
                    ->formatStateUsing(fn (mixed $state): string => Money::number($state, 4) ?? '-')
                    ->placeholder('-')
                    ->visible(fn (PurchaseQuotation $record): bool => strtoupper((string) $record->getAttribute('currency_code')) !== 'TRY'),
                TextEntry::make('payment_terms')
                    ->label(__('purchase_quotation.fields.payment_terms'))
                    ->placeholder('-'),
                TextEntry::make('delivery_terms')
                    ->label(__('purchase_quotation.fields.delivery_terms'))
                    ->placeholder('-'),
                TextEntry::make('lead_time_days')
                    ->label(__('purchase_quotation.fields.lead_time_days'))
                    ->formatStateUsing(fn (mixed $state): string => __('purchase_quotation.values.days', ['n' => (int) $state]))
                    ->placeholder('-'),
                TextEntry::make('delivery_on')
                    ->label(__('purchase_quotation.fields.delivery_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextEntry::make('projects')
                    ->label(__('purchase_quotation.fields.projects'))
                    ->state(fn (PurchaseQuotation $record): array => app(PurchaseQuotationQueries::class)->projectLabels($record))
                    ->listWithLineBreaks()
                    ->placeholder('-'),
                TextEntry::make('order.order_no')
                    ->label(__('purchase_quotation.fields.order'))
                    ->visible(fn (PurchaseQuotation $record): bool => $record->order !== null),
                TextEntry::make('ai_status')
                    ->label(__('purchase_quotation.fields.ai_status'))
                    ->badge()
                    ->visible(fn (PurchaseQuotation $record): bool => $record->aiStatusEnum() !== null),
                TextEntry::make('selectedBy.full_name')
                    ->label(__('purchase_quotation.fields.selected_by'))
                    ->visible(fn (PurchaseQuotation $record): bool => $record->getAttribute('selected_by_personnel_id') !== null),
                TextEntry::make('selected_at')
                    ->label(__('purchase_quotation.fields.selected_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseQuotation $record): bool => $record->getAttribute('selected_at') !== null),
                TextEntry::make('createdBy.full_name')
                    ->label(__('purchase_quotation.fields.created_by'))
                    ->placeholder(__('activity.system')),
                TextEntry::make('archived_at')
                    ->label(__('purchase_quotation.fields.archived_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseQuotation $record): bool => $record->isArchived()),
                TextEntry::make('note')
                    ->label(__('purchase_quotation.fields.note'))
                    ->placeholder('-'),
            ]);
    }

    /** Fiyat bilgileri (ekran goruntusu 6): kayitli toplamlar. */
    private static function pricesSection(): Section
    {
        $money = static fn (PurchaseQuotation $record, float $amount, bool $negative = false): string => ($negative && $amount > 0 ? '-' : '')
            .Money::format($amount, $record->getAttribute('currency_code'), Money::format(0, $record->getAttribute('currency_code')));
        $general = static fn (PurchaseQuotation $record): float => (float) $record->getAttribute('general_discount');

        return Section::make(__('purchase_quotation.sections.prices'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->components([
                TextEntry::make('price_subtotal')
                    ->label(__('purchase_quotation.fields.subtotal'))
                    ->state(fn (PurchaseQuotation $record): string => $money($record, (float) $record->getAttribute('subtotal')))
                    ->inlineLabel()
                    ->alignEnd(),
                TextEntry::make('price_line_discount')
                    ->label(__('purchase_quotation.fields.line_discount'))
                    ->state(fn (PurchaseQuotation $record): string => $money($record, max(0.0, (float) $record->getAttribute('discount_total') - $general($record)), true))
                    ->inlineLabel()
                    ->alignEnd(),
                TextEntry::make('price_general_discount')
                    ->label(__('purchase_quotation.fields.general_discount'))
                    ->state(fn (PurchaseQuotation $record): string => $money($record, $general($record), true))
                    ->inlineLabel()
                    ->alignEnd(),
                TextEntry::make('price_discount_total')
                    ->label(__('purchase_quotation.fields.discount_total'))
                    ->state(fn (PurchaseQuotation $record): string => $money($record, (float) $record->getAttribute('discount_total'), true))
                    ->color('danger')
                    ->weight(FontWeight::SemiBold)
                    ->inlineLabel()
                    ->alignEnd(),
                TextEntry::make('price_total')
                    ->label(__('purchase_quotation.fields.grand_total'))
                    ->state(fn (PurchaseQuotation $record): string => $money($record, (float) $record->getAttribute('total_amount')))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->inlineLabel()
                    ->alignEnd()
                    ->extraEntryWrapperAttributes(['class' => 'kcp-sum-total']),
                TextEntry::make('price_try')
                    ->label(__('purchase_quotation.fields.total_try'))
                    ->state(fn (PurchaseQuotation $record): string => Money::format($record->totalInTry(), 'TRY'))
                    ->visible(fn (PurchaseQuotation $record): bool => strtoupper((string) $record->getAttribute('currency_code')) !== 'TRY')
                    ->inlineLabel()
                    ->alignEnd(),
            ]);
    }

    /** Kalem tablosu: formdaki kalem basligiyla ayni rozetler. */
    private static function linesSection(): Section
    {
        $currency = static fn (PurchaseQuotationLine $record): mixed => $record->quotation?->getAttribute('currency_code');

        return Section::make(__('purchase_quotation.sections.lines'))
            ->icon(Heroicon::OutlinedListBullet)
            ->components([
                RepeatableEntry::make('lines')
                    // Gizli etiket ekran okuyucu icin de Turkce olsun ("Lines" okunuyordu).
                    ->label(__('purchase_quotation.sections.lines'))
                    ->hiddenLabel()
                    ->table([
                        TableColumn::make(__('purchase_quotation.line.no'))->width('3rem'),
                        TableColumn::make(__('purchase_quotation.line.description')),
                        TableColumn::make(__('purchase_quotation.line.catalog_item')),
                        TableColumn::make(__('purchase_quotation.line.need')),
                        TableColumn::make(__('purchase_quotation.line.quantity'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_quotation.line.unit_price'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_quotation.line.discount_rate'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_quotation.line.line_total'))->alignment(Alignment::End),
                        TableColumn::make(__('purchase_quotation.line.flags')),
                    ])
                    ->schema([
                        TextEntry::make('sort_order')
                            ->formatStateUsing(fn (mixed $state): string => (string) ((int) $state + 1)),
                        TextEntry::make('description')
                            ->formatStateUsing(fn (mixed $state, PurchaseQuotationLine $record): string => trim((string) $state.(filled($record->getAttribute('brand_model')) ? ' · '.$record->getAttribute('brand_model') : '')))
                            ->wrap(),
                        TextEntry::make('catalogItem.name')
                            ->placeholder('-')
                            ->wrap(),
                        TextEntry::make('need.title')
                            ->placeholder('-')
                            ->limit(50),
                        TextEntry::make('quantity')
                            ->formatStateUsing(fn (mixed $state, PurchaseQuotationLine $record): string => PurchaseLines::quantityText($state, app(UnitResolver::class)->display($record)))
                            ->alignEnd(),
                        TextEntry::make('unit_price')
                            ->formatStateUsing(fn (mixed $state, PurchaseQuotationLine $record): string => Money::format($state, $currency($record), '-', 4))
                            ->alignEnd(),
                        TextEntry::make('discount_rate')
                            ->formatStateUsing(fn (mixed $state): string => (float) $state > 0 ? '%'.Money::number($state, 2) : '-')
                            ->alignEnd(),
                        TextEntry::make('line_total')
                            ->formatStateUsing(fn (mixed $state, PurchaseQuotationLine $record): string => Money::format($state, $currency($record)))
                            ->weight(FontWeight::SemiBold)
                            ->alignEnd(),
                        TextEntry::make('flags')
                            ->state(fn (PurchaseQuotationLine $record): string => 'x')
                            ->formatStateUsing(fn (PurchaseQuotationLine $record) => PurchaseLines::badges(PurchaseLines::lineBadges($record))),
                    ]),
            ]);
    }
}
