<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\RelationManagers;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\CatalogMatchStatus;
use App\Enums\Procurement\CostLineType;
use App\Enums\Procurement\PriceState;
use App\Exceptions\AbstractException;
use App\Filament\Support\ActionColors;
use App\Filament\Support\CodeLine;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\MoneyDisplay;
use App\Filament\Support\RecordLinks;
use App\Filament\Support\RowDetail;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CostList;
use App\Models\Procurement\CostListLine;
use App\Query\Procurement\CatalogQueries;
use App\Query\Procurement\CostListQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CostListService;
use App\Services\Procurement\PurchaseNeedService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Teklif sayfasinda "Maliyet kalemleri" (B52, D-187 / D-189). Guncel teklif
 * surumune aktarilan maliyet listesi Excel'inin kalemleri; sekmeler Excel'deki
 * gibi Urun/Hizmet, Idari kadro ve Genel giderler.
 *
 * - "Excel'den aktar": teklif kapsamina yuklenmis, bu surume henuz
 *   aktarilmamis maliyet listesi belgesi kalemlere ayrilir; her kalem
 *   katalogdaki var olan kalemle eslesir (kullanicinin kurali: her yuklemede
 *   yeni kayit acilmaz), cok benzeyen kalem "Benzer?" olarak secim bekler.
 * - "Eslestir": benzer ya da yeni kalemi katalogdaki bir kaleme baglar (yazim
 *   esleme adi olur) ya da yeni katalog kalemi olarak bir kez ekler.
 * - "Satin almaya gonder" (satir ve toplu): secilen kalemler siparis kalemi
 *   olur ve proje onayini bekler.
 * - Fiyat durumu Excel'deki satir renginden gelir (yesil kesin, sari
 *   netlesmedi). Varsayilan suzgec "Tutari olanlar".
 */
class CostItemsRelationManager extends RelationManager
{
    /** Teklifte boyle bir iliski yok; kayitlar getRelationship() ile CostListQueries'ten gelir. */
    protected static string $relationship = 'costItems';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCalculator;

    /** @var array<int, string>|null Istek boyunca "Excel'den aktar" secenekleri. */
    private ?array $importOptionsCache = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('cost_items.relation.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return SchemaReadiness::hasBatch('B52A')
            && FeatureFlags::enabled(Feature::CostItems)
            && Gate::allows('viewAny', CostListLine::class);
    }

    public function getRelationship(): Relation | Builder
    {
        return app(CostListQueries::class)->linesForVersion($this->proposal(), $this->versionId());
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $queries = app(CostListQueries::class);
        $counts = $queries->typeCounts($this->versionId());
        $tabs = [];

        foreach (CostLineType::cases() as $type) {
            $tabs[$type->value] = Tab::make(__('cost_items.tabs.'.$type->value))
                ->badge($counts[$type->value] ?? 0)
                ->modifyQueryUsing(static fn (Builder $query): Builder => $queries->ofType($query, $type));
        }

        return $tabs;
    }

    public function table(Table $table): Table
    {
        $queries = app(CostListQueries::class);

        return $table
            ->heading(__('cost_items.relation.title'))
            ->description(fn (): ?string => $this->summaryLine())
            ->recordTitleAttribute('description')
            ->columns([
                // Satir no ayri sutun degil, tanimin altinda kucuk yazi; Excel'deki yeri
                // yaninda (CodeLine, 14 Ekim 2026).
                CodeLine::under(
                    TextColumn::make('description')
                        ->label(__('cost_items.fields.description'))
                        ->tooltip(fn (CostListLine $record): ?string => filled($record->getAttribute('note')) ? (string) $record->getAttribute('note') : null)
                        ->searchable()
                        ->wrap(),
                    'line_no',
                    fn (CostListLine $record): ?string => filled($record->path()) ? $record->path() : null,
                ),
                TextColumn::make('match_status')
                    ->label(__('cost_items.fields.match'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state, CostListLine $record): string => self::matchText($record))
                    ->url(fn (CostListLine $record): ?string => ($item = self::matchItem($record)) !== null ? RecordLinks::detailUrl($item, checkRecord: false) : null)
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label(__('cost_items.fields.quantity'))
                    ->formatStateUsing(fn (mixed $state, CostListLine $record): string => trim((Money::number($state, 2) ?? '-').' '.($record->getAttribute('unit') ?? '')))
                    ->placeholder('-')
                    ->alignEnd(),
                MoneyDisplay::column('unit_cost', fn (?Model $record): mixed => self::currency($record), 4)
                    ->label(__('cost_items.fields.unit_cost')),
                MoneyDisplay::column('total_cost', fn (?Model $record): mixed => self::currency($record))
                    ->label(__('cost_items.fields.total_cost'))
                    ->sortable(),
                // Satis tutari yalniz Urun/Hizmet sayfasinda anlamlidir.
                MoneyDisplay::column('total_sale', fn (?Model $record): mixed => self::currency($record))
                    ->label(__('cost_items.fields.total_sale'))
                    ->visible(fn (): bool => $this->activeType() === CostLineType::ProductService),
                TextColumn::make('price_state')
                    ->label(__('cost_items.fields.price_state'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('purchaseNeed.status')
                    ->label(__('cost_items.fields.purchase_status'))
                    ->badge()
                    ->placeholder('-'),
            ])
            ->filters([
                Filter::make('with_cost')
                    ->label(__('cost_items.filters.with_cost'))
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $queries->withCost($query)),
                SelectFilter::make('price_state')
                    ->label(__('cost_items.filters.price_state'))
                    ->options(PriceState::class)
                    ->native(false),
                SelectFilter::make('match_status')
                    ->label(__('cost_items.filters.match'))
                    ->options(CatalogMatchStatus::class)
                    ->native(false),
                Filter::make('not_sent')
                    ->label(__('cost_items.filters.not_sent'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $queries->notSent($query)),
            ])
            ->headerActions([
                $this->importAction(),
            ])
            ->recordActions([
                // Maliyet satirinin kendi sayfasi yok: satira tiklamak salt okunur ayrinti penceresi (D-125).
                RowDetail::action(),
                $this->matchAction(),
                $this->sendAction(),
            ])
            ->toolbarActions([
                $this->sendBulkAction(),
            ])
            ->emptyStateHeading(__('cost_items.relation.empty'))
            ->emptyStateDescription(__('cost_items.relation.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedCalculator)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    /** "Excel'den aktar": guncel surumun aktarilmamis maliyet listesi belgelerinden biri. */
    private function importAction(): Action
    {
        return Action::make('import_cost_list')
            ->label(__('cost_items.actions.import'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color(ActionColors::CREATE)
            ->modalHeading(__('cost_items.actions.import'))
            ->modalDescription(__('cost_items.import.description'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('cost_items.actions.import'))
            ->visible(fn (): bool => Gate::allows('create', CostList::class) && $this->importOptions() !== [])
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    Select::make('document_id')
                        ->label(__('cost_items.import.document'))
                        ->options(fn (): array => $this->importOptions())
                        ->default(fn (): ?int => count($this->importOptions()) === 1 ? (int) array_key_first($this->importOptions()) : null)
                        ->required()
                        ->native(false),
                ])))
            ->action(function (array $data): void {
                $document = app(CostListQueries::class)->importableDocument($this->proposal(), (int) ($data['document_id'] ?? 0));

                if (! $document instanceof ProposalVersionScopeDocument) {
                    return;
                }

                try {
                    $list = app(CostListService::class)->importScopeDocument($document);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                $summary = app(CostListQueries::class)->importSummary($list);
                DomainNotifications::success(__('cost_items.messages.imported', $summary));
                $this->refreshCounts();
            });
    }

    /** "Eslestir": benzer / yeni kalemi katalogdaki bir kaleme baglar ya da yeni katalog kalemi yapar. */
    private function matchAction(): Action
    {
        return Action::make('match_cost_line')
            ->label(__('cost_items.actions.match'))
            ->icon(Heroicon::OutlinedLink)
            ->color(ActionColors::EDIT)
            ->modalHeading(__('cost_items.actions.match'))
            ->modalDescription(fn (CostListLine $record): string => __('cost_items.match_form.line').': '.$record->getAttribute('description'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('cost_items.actions.match'))
            ->visible(fn (CostListLine $record): bool => in_array($record->getAttribute('match_status'), [CatalogMatchStatus::Similar, CatalogMatchStatus::New], true)
                && $record->purchaseNeed === null
                && Gate::allows('update', $record))
            ->fillForm(fn (CostListLine $record): array => [
                'catalog_item_id' => $record->getAttribute('suggested_catalog_item_id') ?? $record->getAttribute('catalog_item_id'),
                'as_new' => false,
            ])
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    Select::make('catalog_item_id')
                        ->label(__('cost_items.match_form.catalog_item'))
                        ->helperText(__('cost_items.match_form.description'))
                        // D-192: iki satirli secenek (ad / silik kategori yolu); arama duz metinde.
                        ->options(fn (CostListLine $record): array => app(CatalogQueries::class)->itemOptionsHtml('', self::kinds($record)))
                        ->getSearchResultsUsing(fn (string $search, CostListLine $record): array => app(CatalogQueries::class)->itemOptionsHtml($search, self::kinds($record)))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => app(CatalogQueries::class)->itemLabelHtml($value))
                        ->allowHtml()
                        ->searchable()
                        ->native(false)
                        ->required(fn (Get $get): bool => ! (bool) $get('as_new'))
                        ->hidden(fn (Get $get): bool => (bool) $get('as_new')),
                    Toggle::make('as_new')
                        ->label(__('cost_items.match_form.as_new'))
                        ->live()
                        ->columnStart(1),
                ])))
            ->action(function (CostListLine $record, array $data): void {
                $asNew = (bool) ($data['as_new'] ?? false);

                try {
                    app(CostListService::class)->resolveLine($record, $asNew ? null : (int) ($data['catalog_item_id'] ?? 0), $asNew);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('cost_items.messages.matched'));
                $this->refreshCounts();
            });
    }

    /** Satirdaki "Satin almaya gonder". */
    private function sendAction(): Action
    {
        return Action::make('send_cost_line')
            ->label(__('cost_items.actions.send'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(ActionColors::SEND)
            ->modalHeading(__('cost_items.actions.send'))
            ->modalDescription(__('cost_items.send_form.description'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('cost_items.actions.send'))
            ->visible(fn (CostListLine $record): bool => self::canSend($record))
            ->schema(fn (Schema $schema): Schema => self::sendForm($schema))
            ->action(function (CostListLine $record, array $data): void {
                $this->send([$record], $data);
            });
    }

    /** Toplu "Satin almaya gonder" (Idari kadro sayfasinda yok: kadro satin alinmaz). */
    private function sendBulkAction(): BulkAction
    {
        return BulkAction::make('send_cost_lines')
            ->label(__('cost_items.actions.send'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(ActionColors::SEND)
            ->modalHeading(__('cost_items.actions.send'))
            ->modalDescription(__('cost_items.send_form.description'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('cost_items.actions.send'))
            ->visible(fn (): bool => $this->activeType() !== CostLineType::AdminStaff)
            ->authorizeIndividualRecords('update')
            ->schema(fn (Schema $schema): Schema => self::sendForm($schema))
            ->action(function (Collection $records, array $data): void {
                $this->send($records->all(), $data);
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function sendForm(Schema $schema): Schema
    {
        return $schema
            ->columns(FieldGrid::MODAL_COLUMNS)
            ->components(FieldGrid::modal([
                DatePicker::make('needed_by')
                    ->label(__('cost_items.send_form.needed_by'))
                    ->displayFormat('d.m.Y'),
                Toggle::make('is_urgent')
                    ->label(__('cost_items.send_form.is_urgent'))
                    ->default(false),
                Textarea::make('note')
                    ->label(__('cost_items.send_form.note'))
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnStart(1),
            ]));
    }

    /**
     * @param  list<CostListLine>  $lines
     * @param  array<string, mixed>  $data
     */
    private function send(array $lines, array $data): void
    {
        try {
            $result = app(PurchaseNeedService::class)->sendCostLines($lines, [
                'needed_by' => filled($data['needed_by'] ?? null) ? (string) $data['needed_by'] : null,
                'is_urgent' => (bool) ($data['is_urgent'] ?? false),
                'note' => filled($data['note'] ?? null) ? (string) $data['note'] : null,
            ]);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        DomainNotifications::success(__('cost_items.messages.sent', $result));
        $this->refreshCounts();
    }

    /** Tutari olan, katalogla eslesmis, gonderilmemis, satin alinabilir kalem. */
    private static function canSend(CostListLine $line): bool
    {
        return $line->hasCost()
            && $line->getAttribute('catalog_item_id') !== null
            && $line->purchaseNeed === null
            && $line->getAttribute('line_type') !== CostLineType::AdminStaff
            && Gate::allows('update', $line);
    }

    /** Ozet satiri (acik sekmenin tutari olan kalemleri). */
    private function summaryLine(): ?string
    {
        $summary = app(CostListQueries::class)->summary($this->versionId(), $this->activeType());

        if ($summary['count'] === 0) {
            return null;
        }

        return __('cost_items.summary', [
            'count' => $summary['count'],
            'matched' => $summary['matched'],
            'new' => $summary['new'],
            'similar' => $summary['similar'],
            'total' => Money::sums($summary['total']),
            'open' => Money::sums($summary['open'], '0'),
        ]);
    }

    /** Katalog sutunu: eslesen kalemin adi, "Yeni: ad" ya da "Benzer: ad?". */
    private static function matchText(CostListLine $line): string
    {
        $status = $line->getAttribute('match_status');
        $item = self::matchItem($line);
        $name = $item instanceof CatalogItem ? (string) $item->getAttribute('name') : null;

        return match (true) {
            $status === CatalogMatchStatus::Similar && $name !== null => __('cost_items.match.similar', ['name' => $name]),
            $status === CatalogMatchStatus::New && $name !== null => __('cost_items.match.new', ['name' => $name]),
            $name !== null => $name,
            $status instanceof CatalogMatchStatus => (string) $status->getLabel(),
            default => '-',
        };
    }

    /** Eslesen (ya da benzer satirda onerilen) katalog kalemi. */
    private static function matchItem(CostListLine $line): ?CatalogItem
    {
        $item = $line->getAttribute('match_status') === CatalogMatchStatus::Similar
            ? $line->suggestedItem
            : $line->catalogItem;

        return $item instanceof CatalogItem ? $item : null;
    }

    /**
     * Eslestirmede aranacak turler: satirin turunun ailesi (Urun ve Hizmet birlikte).
     *
     * @return list<CatalogItemKind>
     */
    private static function kinds(CostListLine $line): array
    {
        $type = $line->getAttribute('line_type');
        $type = $type instanceof CostLineType ? $type : CostLineType::tryFrom((string) $type);

        return $type?->catalogKind()->family() ?? CatalogItemKind::cases();
    }

    private static function currency(?Model $record): mixed
    {
        return $record instanceof CostListLine
            ? ($record->getAttribute('currency_code') ?: $record->costList?->getAttribute('currency_code'))
            : null;
    }

    private function activeType(): ?CostLineType
    {
        return CostLineType::tryFrom((string) $this->activeTab);
    }

    /**
     * @return array<int, string>
     */
    private function importOptions(): array
    {
        return $this->importOptionsCache ??= app(CostListQueries::class)->importOptions($this->proposal());
    }

    /** Yazmadan sonra sekme rozetleri ve aktarim secenekleri yeniden okunsun. */
    private function refreshCounts(): void
    {
        unset($this->cachedTabs);
        $this->importOptionsCache = null;
    }

    private function versionId(): ?int
    {
        $versionId = $this->proposal()->getAttribute('current_version_id');

        return $versionId !== null ? (int) $versionId : null;
    }

    private function proposal(): Proposal
    {
        /** @var Proposal $proposal */
        $proposal = $this->getOwnerRecord();

        return $proposal;
    }
}
