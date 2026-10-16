<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Platform\Feature;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedActions;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedResource;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\QuoteComparison;
use App\Filament\Support\RecordLinks;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Models\Procurement\PurchaseQuote;
use App\Query\Procurement\PurchaseQuotationQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Authorization\PermissionKey;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Carbon\CarbonInterface;

/**
 * Genel bakis: Satin alma onerileri (D-189). Kullanicinin istegi (10 Ekim
 * 2026): "Yönetim tarafının dashboard'unda satın alma teklifleri için basit
 * küçük bir tablo: x projesi x teklifi için x maliyet kalemlerinin x satın
 * alma teklifi (önerilenler), tutar, termin, siparişin geleceği tarih. Sadece
 * önerilenler gösterilir, isterse satıra tıklayıp diğer teklifleri ve
 * detaylarını modal olarak görebilir." Ust yonetim, tam yetkili ve siparis
 * kalemlerini gorme izni olan gorur; en cok 10 satir.
 *
 * D-192 (B53 ve Satin alma teklifleri acik): tablo secilmis ve henuz
 * siparise donusmemis satin alma teklifleridir; satira tiklamak teklif
 * sayfasini acar. B53 oncesi yukaridaki davranis surer.
 */
class PurchaseRecommendationsWidget extends TableWidget
{
    private const LIMIT = 10;

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! SchemaReadiness::hasBatch('B52A')
            || ! FeatureFlags::enabled(Feature::Dashboard)
            || ! FeatureFlags::enabled(Feature::DashboardPurchasing)
            || ! $user instanceof Personnel) {
            return false;
        }

        // D-192 (B53): tablo secilmis satin alma teklifleridir; ekrani acik olmalidir.
        if (self::quotationMode()) {
            if (! PurchaseQuotationResource::canAccess()) {
                return false;
            }
        } elseif (! FeatureFlags::enabled(Feature::PurchaseNeeds)) {
            return false;
        }

        return app(ExecutiveDirectory::class)->isExecutive($user)
            || app(RoleResolver::class)->hasFullAccess($user)
            || $user->can(PermissionKey::make('viewAny', 'PurchaseNeed'));
    }

    /** B53 uygulandi ve Satin alma teklifleri acik: tablo secilmis teklifleri gosterir. */
    private static function quotationMode(): bool
    {
        return SchemaReadiness::hasBatch('B53') && FeatureFlags::enabled(Feature::PurchaseQuotations);
    }

    public function table(Table $table): Table
    {
        if (self::quotationMode()) {
            return $this->quotationTable($table);
        }

        return $table
            ->heading(__('purchasing.widget.heading'))
            ->query(fn () => app(PurchasingQueries::class)->dashboardRecommendations(self::LIMIT))
            ->headerActions([
                Action::make('show_all')
                    ->label(__('purchasing.actions.show_all'))
                    ->link()
                    ->color('primary')
                    ->visible(fn (): bool => PurchaseNeedResource::canAccess())
                    ->url(fn (): string => PurchaseNeedResource::getUrl('index')),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('purchasing.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart)
            // Satira tiklamak pencereyi acar (sayfaya gitmez); pencerede butun teklifler.
            ->recordUrl(null)
            ->recordAction('open')
            ->columns([
                TextColumn::make('project.name')
                    ->label(__('purchasing.fields.project'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => (string) ($record->project?->display_name ?? $state))
                    ->limit(25)
                    ->placeholder('-'),
                TextColumn::make('proposal_label')
                    ->label(__('purchasing.fields.proposal_title'))
                    ->state(fn (PurchaseNeed $record): ?string => self::proposalLabel($record))
                    ->description(fn (PurchaseNeed $record): ?string => PurchaseNeedResource::potentialJobCode($record))
                    ->limit(35)
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->label(__('purchasing.fields.cost_item'))
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('recommended_supplier')
                    ->label(__('purchasing.fields.recommended_quote'))
                    ->state(fn (PurchaseNeed $record): ?string => $record->recommendedQuote?->supplierLabel())
                    ->icon(Heroicon::Star)
                    ->iconColor('warning')
                    ->limit(25)
                    ->placeholder('-'),
                TextColumn::make('recommended_amount')
                    ->label(__('purchasing.fields.amount'))
                    ->state(fn (PurchaseNeed $record): ?string => $record->recommendedQuote instanceof PurchaseQuote
                        ? Money::format($record->recommendedQuote->getAttribute('total_amount'), $record->recommendedQuote->getAttribute('currency_code'))
                        : null)
                    ->alignEnd()
                    ->placeholder('-'),
                TextColumn::make('needed_by')
                    ->label(__('purchasing.fields.needed_by'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseNeed $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->placeholder('-'),
                TextColumn::make('arrival_on')
                    ->label(__('purchasing.fields.arrival_on'))
                    ->state(fn (PurchaseNeed $record): ?CarbonInterface => self::arrivalOn($record))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('purchasing.fields.status'))
                    ->badge(),
            ])
            ->recordActions([
                // Satir tiklamasiyla acilir; satirda dugme olarak gorunmez (D-125).
                Action::make('open')
                    ->label(__('purchasing.fields.recommended_quote'))
                    ->icon(Heroicon::OutlinedScale)
                    ->modalHeading(fn (PurchaseNeed $record): string => (string) $record->getAttribute('title'))
                    ->modalDescription(fn (PurchaseNeed $record): ?string => self::proposalLabel($record))
                    ->modalIcon(Heroicon::OutlinedShoppingCart)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->schema(fn (PurchaseNeed $record): array => self::detail($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('purchasing.actions.close'))
                    ->extraModalFooterActions(fn (PurchaseNeed $record): array => [
                        Action::make('go_to_need')
                            ->label(__('purchasing.actions.go_to_need'))
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->color(ActionColors::VIEW)
                            ->visible(RecordLinks::detailUrl($record, PurchaseNeedResource::class) !== null)
                            ->url(RecordLinks::detailUrl($record, PurchaseNeedResource::class)),
                    ]),
            ]);
    }

    /**
     * D-192 (B53): secilmis ve henuz siparise donusmemis satin alma teklifleri
     * (proje, kalem ozeti, tedarikci, tutar, TL karsiligi, termin, secilme
     * tarihi); satira tiklamak teklif sayfasini acar.
     */
    private function quotationTable(Table $table): Table
    {
        return $table
            ->heading(__('purchase_quotation.widget.heading'))
            ->query(fn () => app(PurchaseQuotationQueries::class)->selectedWithoutOrder(self::LIMIT))
            ->headerActions([
                Action::make('show_all')
                    ->label(__('purchasing.actions.show_all'))
                    ->link()
                    ->color('primary')
                    ->url(fn (): string => PurchaseQuotationResource::getUrl('index', ['tab' => PurchaseQuotationQueries::TAB_SELECTED])),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('purchase_quotation.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentCurrencyDollar)
            ->recordUrl(fn (PurchaseQuotation $record): ?string => RecordLinks::detailUrl($record, PurchaseQuotationResource::class))
            ->columns([
                TextColumn::make('projects')
                    ->label(__('purchasing.fields.project'))
                    ->state(fn (PurchaseQuotation $record): ?string => self::projectsText($record))
                    ->limit(30)
                    ->placeholder('-'),
                TextColumn::make('items')
                    ->label(__('purchase_quotation.widget.items'))
                    ->state(fn (PurchaseQuotation $record): ?string => self::itemsText($record))
                    ->description(fn (PurchaseQuotation $record): string => (string) $record->getAttribute('quotation_no'))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('supplier_label')
                    ->label(__('purchase_quotation.fields.supplier'))
                    ->state(fn (PurchaseQuotation $record): ?string => $record->supplierLabel() !== '' ? $record->supplierLabel() : null)
                    ->icon(Heroicon::Star)
                    ->iconColor('warning')
                    ->limit(25)
                    ->placeholder('-'),
                TextColumn::make('total_amount')
                    ->label(__('purchasing.fields.amount'))
                    ->formatStateUsing(fn (mixed $state, PurchaseQuotation $record): string => Money::format($state, $record->getAttribute('currency_code')))
                    ->alignEnd(),
                TextColumn::make('total_try')
                    ->label(__('purchase_quotation.fields.total_try'))
                    ->state(fn (PurchaseQuotation $record): ?float => $record->totalInTry())
                    ->formatStateUsing(fn (mixed $state): string => Money::format($state, 'TRY'))
                    ->placeholder('-')
                    ->alignEnd(),
                TextColumn::make('delivery_on')
                    ->label(__('purchasing.fields.needed_by'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('selected_at')
                    ->label(__('purchase_quotation.fields.selected_at'))
                    ->dateTime('d.m.Y')
                    ->placeholder('-'),
            ]);
    }

    /** Teklif kalemlerinin projeleri: tek proje adi ya da "3 proje". */
    private static function projectsText(PurchaseQuotation $record): ?string
    {
        $labels = app(PurchaseQuotationQueries::class)->projectLabels($record);

        return match (count($labels)) {
            0 => null,
            1 => $labels[0],
            default => __('purchase_quotation.values.several_projects', ['count' => count($labels)]),
        };
    }

    /** Ilk iki kalem ve kalan sayi: "Kablo · Pano +3 kalem". */
    private static function itemsText(PurchaseQuotation $record): ?string
    {
        $lines = $record->lines->filter(fn (PurchaseQuotationLine $line): bool => $line->counts())->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $text = $lines->take(2)->map(fn (PurchaseQuotationLine $line): string => (string) $line->getAttribute('description'))->implode(' · ');

        return $lines->count() > 2 ? $text.' '.__('purchase_quotation.widget.more_items', ['count' => $lines->count() - 2]) : $text;
    }

    /**
     * Pencere: kalemin ozeti, onay durumu ve butun teklif kartlari.
     *
     * @return array<int, mixed>
     */
    private static function detail(PurchaseNeed $record): array
    {
        $state = PurchaseNeedActions::approvalState($record);

        return [
            Section::make(__('purchasing.sections.item'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->compact()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make('project.name')
                        ->label(__('purchasing.fields.project'))
                        ->formatStateUsing(fn (mixed $state): string => (string) ($record->project?->display_name ?? $state))
                        ->placeholder('-'),
                    TextEntry::make('proposal.proposal_no')
                        ->label(__('purchasing.fields.proposal'))
                        ->placeholder('-'),
                    TextEntry::make('quantity')
                        ->label(__('purchasing.fields.quantity'))
                        ->formatStateUsing(fn (): string => PurchaseNeedResource::quantityText($record)),
                    TextEntry::make('needed_by')
                        ->label(__('purchasing.fields.needed_by'))
                        ->date('d.m.Y')
                        ->color($record->isOverdue() ? 'danger' : null)
                        ->placeholder('-'),
                    TextEntry::make('arrival_on')
                        ->label(__('purchasing.fields.arrival_on'))
                        ->state(self::arrivalOn($record))
                        ->date('d.m.Y')
                        ->placeholder('-'),
                    TextEntry::make('status')
                        ->label(__('purchasing.fields.status'))
                        ->badge(),
                    TextEntry::make('approval_required')
                        ->label(__('purchasing.fields.approval_required'))
                        ->state($state->required ? __('purchasing.values.yes_required') : __('purchasing.values.not_required'))
                        ->badge()
                        ->color($state->required ? 'warning' : 'success'),
                    TextEntry::make('approval_amount')
                        ->label(__('purchasing.fields.amount_try'))
                        ->state(Money::format($state->amountTry, 'TRY'))
                        ->visible($state->required),
                    TextEntry::make('approval_approvers')
                        ->label(__('purchasing.fields.approvers'))
                        ->state($state->approvers)
                        ->listWithLineBreaks()
                        ->icon(RecordLinks::PERSONNEL_ICON)
                        ->placeholder('-')
                        ->visible($state->required),
                    TextEntry::make('approval_missing')
                        ->label(__('purchasing.fields.missing'))
                        ->state(PurchaseNeedActions::missingText($state))
                        ->color('danger')
                        ->visible($state->required && ! $state->satisfied && PurchaseNeedActions::missingText($state) !== null),
                ]),
            Section::make(__('purchasing.sections.quotes'))
                ->icon(Heroicon::OutlinedScale)
                ->compact()
                ->visible(FeatureFlags::enabled(Feature::PurchaseQuotes))
                ->schema(QuoteComparison::cards(app(PurchasingQueries::class)->quotesOf($record))),
        ];
    }

    private static function proposalLabel(PurchaseNeed $record): ?string
    {
        $proposal = $record->proposal;

        if ($proposal === null) {
            return null;
        }

        return implode(' · ', array_filter([
            (string) $proposal->getAttribute('proposal_no'),
            (string) $proposal->getAttribute('title'),
        ], static fn (string $part): bool => $part !== ''));
    }

    /** Siparisin gelecegi tarih: siparis verildiyse beklenen geliş, yoksa onerilen teklifin teslim tarihi. */
    private static function arrivalOn(PurchaseNeed $record): ?CarbonInterface
    {
        return $record->getAttribute('expected_delivery_on') ?? $record->recommendedQuote?->getAttribute('delivery_on');
    }
}
