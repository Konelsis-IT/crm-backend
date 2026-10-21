<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseNeeds;

use App\Filament\NavigationGroup;
use App\Enums\Platform\Feature;
use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Enums\Procurement\UsageArea;
use App\Filament\Resources\PurchaseNeeds\Pages\EditPurchaseNeed;
use App\Filament\Resources\PurchaseNeeds\Pages\ListPurchaseNeeds;
use App\Filament\Resources\PurchaseNeeds\Pages\ViewPurchaseNeed;
use App\Filament\Support\CodeLine;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\QuoteComparison;
use App\Filament\Support\RecordLinks;
use App\Filament\Support\UnitSelect;
use App\Models\Acquisition\BusinessCase;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CostListLine;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Models\Procurement\PurchaseQuote;
use App\Query\Procurement\PurchaseNeedQueries;
use App\Query\Procurement\PurchaseRequestQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Satin Alma > Satin alinacaklar (D-192'ye kadar "Siparis kalemleri"; B53'te
 * Talep / Teklif / Siparis sutunlari, toplu "Teklif iste" ve "Mevcut teklife
 * ekle"). B52, D-189. Kullanicinin istegi (10 Ekim
 * 2026): "Tümü / Teklif grubunun gönderdiği kalemler / Projenin onay verdiği
 * kalemler / Termin süresi geçmiş / Acil .. gibi sekmeyle siparişini
 * oluşturması gereken kalemler olacak." Departmanlar sirket ici ihtiyaclarini
 * buradan bildirir; satin alma tedarikci tekliflerini kartlarla karsilastirir,
 * en uygununu onerir, onay sinirina gore onaya gonderir, siparis verir ve
 * tamamlar (onaysiz tamamlanmaz). Silme yok, arsiv var (D-156).
 */
class PurchaseNeedResource extends Resource
{
    protected static ?string $model = PurchaseNeed::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::OperationsPurchasing;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('purchasing.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchasing.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchasing.navigation');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::PurchaseNeeds)
            && SchemaReadiness::hasBatch('B52A')
            && parent::canAccess();
    }

    /** Kisi yalniz gorebildigi kalemleri gorur (satin alma ve ust yonetim hepsini). */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return app(PurchasingQueries::class)->withListRelations(
            app(PurchaseNeedQueries::class)->visibleTo(parent::getEloquentQuery(), $user instanceof Personnel ? $user : null),
        );
    }

    /** Duzenle: talep bilgileri (durum ve teklifler eylemlerle degisir). */
    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('purchasing.sections.item'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('title')
                        ->label(__('purchasing.fields.title'))
                        ->required()
                        ->maxLength(495)
                        ->columnSpan(FieldGrid::WIDE),
                    TextInput::make('quantity')
                        ->label(__('purchasing.fields.quantity'))
                        ->numeric()
                        ->minValue(0.0001)
                        ->required()
                        // Onaya gittikten sonra miktar degismez (servis kurali).
                        ->disabled(fn (?PurchaseNeed $record): bool => $record !== null && in_array($record->statusEnum(), [
                            PurchaseNeedStatus::AwaitingApproval, PurchaseNeedStatus::Approved, PurchaseNeedStatus::Ordered,
                        ], true)),
                    // D-194 (B54): sabit birim listesi; kalem listedeki bir urunse onun birimi (kilitli).
                    UnitSelect::enabled()
                        ? UnitSelect::forItem(__('purchasing.fields.unit'), fn (Get $get, ?Model $record): mixed => $record?->getAttribute('catalog_item_id'))
                        : TextInput::make('unit')
                            ->label(__('purchasing.fields.unit'))
                            ->maxLength(40)
                            // D-192: birim kisa alandir.
                            ->columnSpan(FieldGrid::SHORT),
                    DatePicker::make('needed_by')
                        ->label(__('purchasing.fields.needed_by'))
                        ->displayFormat('d.m.Y'),
                    Toggle::make('is_urgent')
                        ->label(__('purchasing.fields.is_urgent'))
                        ->inline(false),
                    ToggleButtons::make('usage_area')
                        ->label(__('purchasing.fields.usage_area'))
                        ->options(UsageArea::class)
                        ->inline(),
                    TextInput::make('brand_model')
                        ->label(__('purchasing.fields.requested_brand_model'))
                        ->maxLength(200),
                    Textarea::make('specification')
                        ->label(__('purchasing.fields.specification'))
                        ->helperText(__('purchasing.help.specification'))
                        ->rows(3)
                        ->maxLength(4000),
                    Textarea::make('justification')
                        ->label(__('purchasing.fields.justification'))
                        ->rows(3)
                        ->maxLength(4000),
                    Textarea::make('existing_check')
                        ->label(__('purchasing.fields.existing_check'))
                        ->rows(3)
                        ->maxLength(4000),
                    Textarea::make('note')
                        ->label(__('purchasing.fields.note'))
                        ->rows(3)
                        ->maxLength(4000),
                    Hidden::make('row_version'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('purchasing.fields.title'))
                    ->description(fn (PurchaseNeed $record): ?string => $record->hasCatalogItem()
                        ? $record->catalogItem?->category?->path()
                        : __('purchasing.values.not_in_catalog'))
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->hasCatalogItem() ? $record->catalogItem?->kindEnum()->getIcon() : Heroicon::OutlinedQuestionMarkCircle)
                    ->iconColor(fn (PurchaseNeed $record): ?string => $record->hasCatalogItem() ? $record->catalogItem?->kindEnum()->getColor() : 'danger')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
                // 15 Ekim 2026: Kaynak ve Talep tek sutun. Kaynak rozeti, altinda kalemin
                // acildigi satin alma talebinin SAT kodu (D-192, B53); talep varsa
                // sutun talep sayfasina baglanir, SAT koduyla aranir.
                self::b53()
                    ? CodeLine::under(
                        TextColumn::make('purchaseRequest.request_no')
                            ->label(__('purchasing.fields.source'))
                            ->state(fn (PurchaseNeed $record): mixed => $record->getAttribute('source'))
                            ->badge()
                            ->searchable(),
                        'purchaseRequest.request_no',
                    )
                    : TextColumn::make('source')
                        ->label(__('purchasing.fields.source'))
                        ->badge(),
                // Teklifin adi; altinda TKLF ve POTIS kodu (CodeLine, 14 Ekim 2026).
                CodeLine::under(
                    TextColumn::make('proposal.title')
                        ->label(__('purchasing.fields.proposal'))
                        ->limit(30)
                        ->placeholder('-')
                        ->searchable(['title', 'proposal_no']),
                    'proposal.proposal_no',
                    fn (PurchaseNeed $record): ?string => self::potentialJobCode($record),
                ),
                TextColumn::make('project.name')
                    ->label(__('purchasing.fields.project'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => (string) ($record->project?->display_name ?? $state))
                    ->limit(30)
                    ->placeholder('-'),
                TextColumn::make('quantity')
                    ->label(__('purchasing.fields.quantity'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => self::quantityText($record))
                    ->alignEnd(),
                TextColumn::make('estimated_total')
                    ->label(__('purchasing.fields.estimated_total'))
                    ->state(fn (PurchaseNeed $record): ?float => self::estimatedTotal($record))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => Money::format($state, $record->getAttribute('estimated_currency_code')))
                    ->placeholder('-')
                    ->alignEnd()
                    ->toggleable(),
                // D-192 (B53): kalemin girdigi satin alma teklifi ve siparisi; eski
                // kalem basina "Önerilen teklif" teklif akisi acikken gizlenir.
                ...(self::b53() ? [
                    PurchaseNeedColumns::quotation(),
                    PurchaseNeedColumns::order(),
                ] : []),
                ...(! PurchaseNeedActions::legacyFlow() ? [] : [
                    TextColumn::make('recommended_quote')
                        ->label(__('purchasing.fields.recommended_quote'))
                        ->state(fn (PurchaseNeed $record): ?string => $record->recommendedQuote?->supplierLabel())
                        ->description(fn (PurchaseNeed $record): ?string => $record->recommendedQuote instanceof PurchaseQuote
                            ? Money::format($record->recommendedQuote->getAttribute('total_amount'), $record->recommendedQuote->getAttribute('currency_code'))
                            : null)
                        ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->recommendedQuote instanceof PurchaseQuote ? Heroicon::Star : null)
                        ->iconColor('warning')
                        ->placeholder('-'),
                ]),
                TextColumn::make('needed_by')
                    ->label(__('purchasing.fields.needed_by'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseNeed $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->placeholder('-')
                    ->sortable(),
                // 15 Ekim 2026: ayri Acil ve Kullanim yeri sutunlari yok; acil kalemin
                // durum rozetinde ates simgesi, altinda kullanim yeri (Sirket ici / Saha).
                TextColumn::make('status')
                    ->label(__('purchasing.fields.status'))
                    ->badge()
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->getAttribute('is_urgent') ? Heroicon::Fire : null)
                    ->iconColor(fn (PurchaseNeed $record): ?string => $record->getAttribute('is_urgent') ? 'danger' : null)
                    ->tooltip(fn (PurchaseNeed $record): ?string => $record->getAttribute('is_urgent') ? __('purchasing.values.urgent') : null)
                    ->description(fn (PurchaseNeed $record): ?string => $record->getAttribute('usage_area')?->getLabel()),
            ])
            ->filters([
                // Arsiv (D-156): varsayilan yalniz aktif kalemler.
                SelectFilter::make('archive')
                    ->label(__('purchasing.filters.archive'))
                    ->options([
                        'active' => __('purchasing.filters.archive_active'),
                        'archived' => __('purchasing.filters.archive_archived'),
                        'all' => __('purchasing.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(PurchaseNeedQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
                SelectFilter::make('status')
                    ->label(__('purchasing.fields.status'))
                    ->options(PurchaseNeedStatus::class)
                    ->native(false),
                SelectFilter::make('source')
                    ->label(__('purchasing.fields.source'))
                    ->options(PurchaseNeedSource::class)
                    ->native(false),
                Filter::make('urgent')
                    ->label(__('purchasing.filters.urgent'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => app(PurchasingQueries::class)->urgentScope($query)),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (PurchaseNeed $record): bool => Gate::allows('update', $record)),
                PurchaseNeedActions::archive(),
                PurchaseNeedActions::restore(),
            ])
            ->toolbarActions([
                PurchaseNeedActions::approveProjectBulk(),
                // D-192 (B53): secilen kalemler topluca satin alma teklifinde birlesir.
                PurchaseNeedActions::requestQuotationBulk(),
                PurchaseNeedActions::addToQuotationBulk(),
            ])
            ->emptyStateHeading(__('purchasing.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            // 15 Ekim 2026: acil kalem acik kirmizi satir ve listenin en ustunde.
            ->recordClasses(fn (PurchaseNeed $record): ?string => $record->getAttribute('is_urgent') ? 'kc-urgent-row' : null)
            ->defaultSort(fn (Builder $query): Builder => app(PurchaseNeedQueries::class)->urgentFirst($query));
    }

    public static function infolist(Schema $schema): Schema
    {
        // D-192: teklif akisi acikken (B53 + Satin alma teklifleri) kalem basina
        // onay / siparis bolumleri ve tedarikci teklif kartlari yerine kalemin
        // satin alma teklifi satirlari ve siparisi gorunur.
        $flow = PurchaseNeedActions::quotationFlow();
        // D-229: eski kalem basina onay / siparis bolumleri yalniz B53 oncesi veritabaninda.
        $legacy = PurchaseNeedActions::legacyFlow();

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])->components([
                self::itemSection(),
                self::sourceSection(),
                self::requestSection(),
                ...($legacy ? [self::approvalSection(), self::orderSection()] : []),
            ]),
            ...($flow ? [self::quotationLinesSection()] : []),
            Section::make(__('purchasing.sections.quotes'))
                ->icon(Heroicon::OutlinedScale)
                ->visible(fn (): bool => FeatureFlags::enabled(Feature::PurchaseQuotes) && $legacy)
                ->schema(fn (PurchaseNeed $record): array => QuoteComparison::cards(
                    app(PurchasingQueries::class)->quotesOf($record),
                    PurchaseNeedActions::quoteCardActions($record),
                )),
        ]);
    }

    /** B53 (kalemli talep, satin alma teklifleri ve siparisler) uygulandi mi? */
    public static function b53(): bool
    {
        return SchemaReadiness::hasBatch('B53');
    }

    /**
     * "Satin alma teklifleri" (D-192): kalemin girdigi her teklif satiri
     * (teklif kodu, tedarikci, miktar, birim fiyat, tutar, teklif durumu,
     * secilen teklif) ve satirin siparisi (SIP kodu ve durumu).
     */
    private static function quotationLinesSection(): Section
    {
        return Section::make(__('purchasing.sections.quotation_lines'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->components([
                RepeatableEntry::make('quotation_lines')
                    ->hiddenLabel()
                    ->state(fn (PurchaseNeed $record): array => app(PurchaseRequestQueries::class)->quotationLinesOf($record))
                    ->placeholder(__('purchasing.values.no_quotation'))
                    ->table([
                        TableColumn::make(__('purchasing.fields.quotation')),
                        TableColumn::make(__('purchasing.fields.supplier')),
                        TableColumn::make(__('purchasing.fields.quotation_quantity')),
                        TableColumn::make(__('purchasing.fields.unit_price')),
                        TableColumn::make(__('purchasing.fields.line_total')),
                        TableColumn::make(__('purchasing.fields.quotation_status')),
                        TableColumn::make(__('purchasing.fields.order')),
                    ])
                    ->schema([
                        TextEntry::make('quotation_no')
                            ->state(fn (PurchaseQuotationLine $record): ?string => $record->quotation?->getAttribute('quotation_no'))
                            ->url(fn (PurchaseQuotationLine $record): ?string => $record->quotation instanceof PurchaseQuotation ? RecordLinks::detailUrl($record->quotation, checkRecord: false) : null)
                            ->icon(fn (PurchaseQuotationLine $record): mixed => $record->quotation instanceof PurchaseQuotation ? RecordLinks::iconFor($record->quotation) : null)
                            ->weight('medium'),
                        TextEntry::make('supplier')
                            ->state(fn (PurchaseQuotationLine $record): ?string => $record->quotation?->supplierLabel())
                            ->placeholder('-'),
                        TextEntry::make('quantity')
                            ->state(fn (PurchaseQuotationLine $record): string => trim((Money::number($record->getAttribute('quantity'), 2) ?? '-').' '.(string) app(UnitResolver::class)->display($record))),
                        TextEntry::make('unit_price')
                            ->state(fn (PurchaseQuotationLine $record): string => Money::format($record->getAttribute('unit_price'), $record->quotation?->getAttribute('currency_code'), '-', 4)),
                        TextEntry::make('line_total')
                            ->state(fn (PurchaseQuotationLine $record): string => Money::format($record->getAttribute('line_total'), $record->quotation?->getAttribute('currency_code')))
                            ->weight('bold'),
                        TextEntry::make('quotation_status')
                            ->state(fn (PurchaseQuotationLine $record): ?string => $record->quotation?->statusEnum()->getLabel())
                            ->badge()
                            ->color(fn (PurchaseQuotationLine $record): ?string => $record->quotation?->statusEnum()->getColor())
                            ->icon(fn (PurchaseQuotationLine $record): mixed => in_array($record->quotation?->statusEnum(), [PurchaseQuotationStatus::Selected, PurchaseQuotationStatus::Ordered], true) ? Heroicon::Star : null)
                            ->tooltip(fn (PurchaseQuotationLine $record): ?string => in_array($record->quotation?->statusEnum(), [PurchaseQuotationStatus::Selected, PurchaseQuotationStatus::Ordered], true) ? __('purchasing.values.selected_quotation') : null),
                        TextEntry::make('order_no')
                            ->state(fn (PurchaseQuotationLine $record): ?string => $record->orderLine?->order?->getAttribute('order_no'))
                            ->badge()
                            ->color(fn (PurchaseQuotationLine $record): ?string => $record->orderLine?->order?->statusEnum()->getColor())
                            ->tooltip(fn (PurchaseQuotationLine $record): ?string => $record->orderLine?->order?->statusEnum()->getLabel())
                            ->url(fn (PurchaseQuotationLine $record): ?string => $record->orderLine?->order instanceof PurchaseOrder ? RecordLinks::detailUrl($record->orderLine->order, checkRecord: false) : null)
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseNeeds::route('/'),
            'view' => ViewPurchaseNeed::route('/{record}'),
            'edit' => EditPurchaseNeed::route('/{record}/edit'),
        ];
    }

    /** Teklifin potansiyel isinin POTIS kodu (D-132: teklifin yaninda her zaman). */
    public static function potentialJobCode(PurchaseNeed $record): ?string
    {
        $case = $record->proposal?->businessCase;

        return $case instanceof BusinessCase ? $case->caseCode()?->getAttribute('formatted_code') : null;
    }

    public static function quantityText(PurchaseNeed $record): string
    {
        return trim((Money::number($record->getAttribute('quantity'), 2) ?? '-').' '.(string) app(UnitResolver::class)->display($record));
    }

    public static function estimatedTotal(PurchaseNeed $record): ?float
    {
        $unitCost = $record->getAttribute('estimated_unit_cost');

        return $unitCost !== null ? (float) $record->getAttribute('quantity') * (float) $unitCost : null;
    }

    private static function itemSection(): Section
    {
        return Section::make(__('purchasing.sections.item'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextEntry::make('catalogItem.name')
                    ->label(__('purchasing.fields.catalog_item'))
                    ->placeholder(__('purchasing.values.not_in_catalog')),
                // Kalem adi sayfanin alt basliginda; kartta tekrar yazilmaz (D-205).
                TextEntry::make('quantity')
                    ->label(__('purchasing.fields.quantity'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => self::quantityText($record)),
                TextEntry::make('status')
                    ->label(__('purchasing.fields.status'))
                    ->badge(),
                TextEntry::make('estimated_unit_cost')
                    ->label(__('purchasing.fields.estimated_unit_cost'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => Money::format($state, $record->getAttribute('estimated_currency_code'), '-', 4))
                    ->placeholder('-'),
                TextEntry::make('estimated_total')
                    ->label(__('purchasing.fields.estimated_total'))
                    ->state(fn (PurchaseNeed $record): ?float => self::estimatedTotal($record))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => Money::format($state, $record->getAttribute('estimated_currency_code')))
                    ->placeholder('-'),
                TextEntry::make('needed_by')
                    ->label(__('purchasing.fields.needed_by'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseNeed $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->placeholder('-'),
                TextEntry::make('urgency')
                    ->label(__('purchasing.fields.is_urgent'))
                    ->state(fn (PurchaseNeed $record): string => $record->getAttribute('is_urgent') ? __('purchasing.values.urgent') : __('purchasing.values.normal'))
                    ->badge()
                    ->color(fn (PurchaseNeed $record): string => $record->getAttribute('is_urgent') ? 'danger' : 'gray')
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->getAttribute('is_urgent') ? Heroicon::Fire : null),
                TextEntry::make('note')
                    ->label(__('purchasing.fields.note'))
                    ->placeholder('-'),
                TextEntry::make('archived_at')
                    ->label(__('purchasing.fields.archived_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseNeed $record): bool => $record->isArchived()),
            ]);
    }

    private static function sourceSection(): Section
    {
        return Section::make(__('purchasing.sections.source'))
            ->icon(Heroicon::OutlinedDocumentCurrencyDollar)
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextEntry::make('source')
                    ->label(__('purchasing.fields.source'))
                    ->badge(),
                // D-192 (B53): kalemli satin alma talebi (SAT kodu, talep sayfasina baglanti).
                ...(self::b53() ? [
                    TextEntry::make('purchaseRequest.request_no')
                        ->label(__('purchasing.fields.purchase_request'))
                        ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('purchase_request_id') !== null),
                ] : []),
                TextEntry::make('proposal.proposal_no')
                    ->label(__('purchasing.fields.proposal'))
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('proposal_id') !== null),
                TextEntry::make('potential_job')
                    ->label(__('purchasing.fields.potential_job'))
                    ->state(fn (PurchaseNeed $record): ?string => self::potentialJobCode($record))
                    ->url(fn (PurchaseNeed $record): ?string => $record->proposal?->businessCase instanceof BusinessCase ? RecordLinks::detailUrl($record->proposal->businessCase, checkRecord: false) : null)
                    ->icon(fn (PurchaseNeed $record): mixed => $record->proposal?->businessCase instanceof BusinessCase ? RecordLinks::iconFor($record->proposal->businessCase) : null)
                    ->placeholder('-')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('proposal_id') !== null),
                TextEntry::make('project.name')
                    ->label(__('purchasing.fields.project'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => (string) ($record->project?->display_name ?? $state))
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('project_id') !== null),
                TextEntry::make('excel_path')
                    ->label(__('purchasing.fields.excel_path'))
                    ->state(fn (PurchaseNeed $record): ?string => self::excelPath($record->costListLine))
                    ->placeholder('-')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('cost_list_line_id') !== null),
                TextEntry::make('costListLine.price_state')
                    ->label(__('purchasing.fields.price_state'))
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('cost_list_line_id') !== null),
                TextEntry::make('requestedBy.full_name')
                    ->label(__('purchasing.fields.requested_by'))
                    ->placeholder(__('activity.system')),
                TextEntry::make('orgUnit.name')
                    ->label(__('purchasing.fields.org_unit'))
                    ->placeholder('-'),
                TextEntry::make('projectApprovedBy.full_name')
                    ->label(__('purchasing.fields.project_approved_by'))
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('project_approved_at') !== null),
                TextEntry::make('project_approved_at')
                    ->label(__('purchasing.fields.project_approved_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('project_approved_at') !== null),
            ]);
    }

    /**
     * Satin alma talebi (sat-03, D-190): kullanim yeri, talep edilen urun,
     * istenen marka / model ve ozellikler, gerekce, var olanin neden
     * kullanilamadigi. Bilgi girilmemis talepte bolum gorunmez.
     */
    private static function requestSection(): Section
    {
        $filled = static fn (PurchaseNeed $record, string $field): bool => filled($record->getAttribute($field));

        return Section::make(__('purchasing.sections.request'))
            ->icon(Heroicon::OutlinedHandRaised)
            ->columns(['default' => 1, 'md' => 2])
            ->visible(fn (PurchaseNeed $record): bool => $record->usageEnum() !== null
                || collect(['requested_item_name', 'brand_model', 'specification', 'justification', 'existing_check'])->contains(fn (string $field): bool => $filled($record, $field)))
            ->components([
                TextEntry::make('usage_area')
                    ->label(__('purchasing.fields.usage_area'))
                    ->state(fn (PurchaseNeed $record): ?UsageArea => $record->usageEnum())
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('requested_item_name')
                    ->label(__('purchasing.fields.requested_item_name'))
                    ->visible(fn (PurchaseNeed $record): bool => $filled($record, 'requested_item_name')),
                TextEntry::make('brand_model')
                    ->label(__('purchasing.fields.requested_brand_model'))
                    ->visible(fn (PurchaseNeed $record): bool => $filled($record, 'brand_model')),
                TextEntry::make('specification')
                    ->label(__('purchasing.fields.specification'))
                    ->visible(fn (PurchaseNeed $record): bool => $filled($record, 'specification')),
                TextEntry::make('justification')
                    ->label(__('purchasing.fields.justification'))
                    ->visible(fn (PurchaseNeed $record): bool => $filled($record, 'justification')),
                TextEntry::make('existing_check')
                    ->label(__('purchasing.fields.existing_check'))
                    ->visible(fn (PurchaseNeed $record): bool => $filled($record, 'existing_check')),
            ]);
    }

    private static function approvalSection(): Section
    {
        $required = fn (PurchaseNeed $record): bool => PurchaseNeedActions::approvalState($record)->required;

        return Section::make(__('purchasing.sections.approval'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextEntry::make('approval_required')
                    ->label(__('purchasing.fields.approval_required'))
                    ->state(fn (PurchaseNeed $record): string => $required($record) ? __('purchasing.values.yes_required') : __('purchasing.values.not_required'))
                    ->badge()
                    ->color(fn (PurchaseNeed $record): string => $required($record) ? 'warning' : 'success'),
                TextEntry::make('approval_progress')
                    ->label(__('purchasing.fields.status'))
                    ->state(fn (PurchaseNeed $record): string => self::approvalProgress($record))
                    ->badge()
                    ->color(fn (PurchaseNeed $record): string => PurchaseNeedActions::approvalState($record)->satisfied ? 'success' : 'warning')
                    ->visible($required),
                TextEntry::make('approval_amount')
                    ->label(__('purchasing.fields.amount_try'))
                    ->state(fn (PurchaseNeed $record): string => Money::format(PurchaseNeedActions::approvalState($record)->amountTry, 'TRY'))
                    ->weight('bold')
                    ->visible($required),
                TextEntry::make('approval_policy')
                    ->label(__('purchasing.fields.policy'))
                    ->state(fn (PurchaseNeed $record): ?string => PurchaseNeedActions::approvalState($record)->policyName)
                    ->placeholder('-')
                    ->visible($required),
                TextEntry::make('approval_approvers')
                    ->label(__('purchasing.fields.approvers'))
                    ->state(fn (PurchaseNeed $record): array => PurchaseNeedActions::approvalState($record)->approvers)
                    ->listWithLineBreaks()
                    ->icon(RecordLinks::PERSONNEL_ICON)
                    ->placeholder('-')
                    ->visible($required),
                TextEntry::make('approvalRequest.status')
                    ->label(__('purchasing.fields.approval_request'))
                    ->badge()
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('approval_request_id') !== null),
                TextEntry::make('approval_missing')
                    ->label(__('purchasing.fields.missing'))
                    ->state(fn (PurchaseNeed $record): ?string => PurchaseNeedActions::missingText(PurchaseNeedActions::approvalState($record)))
                    ->color('danger')
                    ->visible(fn (PurchaseNeed $record): bool => PurchaseNeedActions::blocked($record)
                        && PurchaseNeedActions::missingText(PurchaseNeedActions::approvalState($record)) !== null),
            ]);
    }

    private static function orderSection(): Section
    {
        return Section::make(__('purchasing.sections.order'))
            ->icon(Heroicon::OutlinedTruck)
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextEntry::make('recommended_quote')
                    ->label(__('purchasing.fields.recommended_quote'))
                    ->state(fn (PurchaseNeed $record): ?string => $record->recommendedQuote instanceof PurchaseQuote
                        ? $record->recommendedQuote->supplierLabel().' · '.Money::format($record->recommendedQuote->getAttribute('total_amount'), $record->recommendedQuote->getAttribute('currency_code'))
                        : null)
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->recommendedQuote instanceof PurchaseQuote ? Heroicon::Star : null)
                    ->iconColor('warning')
                    ->placeholder(__('purchasing.values.no_quote')),
                TextEntry::make('recommendedBy.full_name')
                    ->label(__('purchasing.fields.recommended_by'))
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('recommended_by_personnel_id') !== null),
                TextEntry::make('orderedBy.full_name')
                    ->label(__('purchasing.fields.ordered_by'))
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('ordered_by_personnel_id') !== null),
                TextEntry::make('ordered_at')
                    ->label(__('purchasing.fields.ordered_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('ordered_at') !== null),
                TextEntry::make('expected_delivery_on')
                    ->label(__('purchasing.fields.expected_delivery_on'))
                    ->date('d.m.Y')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('expected_delivery_on') !== null),
                TextEntry::make('completedBy.full_name')
                    ->label(__('purchasing.fields.completed_by'))
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('completed_by_personnel_id') !== null),
                TextEntry::make('completed_at')
                    ->label(__('purchasing.fields.completed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('completed_at') !== null),
                TextEntry::make('cancelled_at')
                    ->label(__('purchasing.fields.cancelled_at'))
                    ->dateTime('d.m.Y H:i')
                    ->visible(fn (PurchaseNeed $record): bool => $record->getAttribute('cancelled_at') !== null),
            ]);
    }

    private static function approvalProgress(PurchaseNeed $record): string
    {
        $state = PurchaseNeedActions::approvalState($record);

        return match (true) {
            $state->satisfied => __('purchasing.values.satisfied'),
            $state->pending => __('purchasing.values.pending'),
            default => __('purchasing.values.not_requested'),
        };
    }

    /** Excel'deki yer: "Insaat › Saha Duzeltme · Maliyet sayfasi, 42. satir". */
    private static function excelPath(?CostListLine $line): ?string
    {
        if (! $line instanceof CostListLine) {
            return null;
        }

        $parts = array_filter([
            $line->path(),
            filled($line->getAttribute('excel_sheet')) && $line->getAttribute('excel_row') !== null
                ? __('purchasing.values.excel_row', ['sheet' => $line->getAttribute('excel_sheet'), 'row' => $line->getAttribute('excel_row')])
                : null,
        ], static fn (mixed $part): bool => filled($part));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
