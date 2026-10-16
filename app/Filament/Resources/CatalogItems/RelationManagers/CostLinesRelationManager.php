<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\RelationManagers;

use App\Enums\Platform\Feature;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Support\CodeLine;
use App\Filament\Support\MoneyDisplay;
use App\Filament\Support\RecordLinks;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CostListLine;
use App\Models\Project\Project;
use App\Query\Procurement\CostListQueries;
use App\Services\Platform\FeatureFlags;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

/**
 * Katalog kalemi sayfasinda "Teklif ve projeler" (D-189: kalemin hangi teklif
 * ya da proje icin istendigi). Kalemi isteyen teklif maliyet satirlari: teklif
 * numarasi (TKLF) ve potansiyel isin POTIS kodu, proje (siparis kalemindeki ya
 * da teklifin potansiyel isinin projesi), Excel'deki yeri, miktar, birim ve
 * toplam maliyet, fiyat durumu, satin alma durumu. Salt okunur; satira
 * tiklamak teklifi acar.
 */
class CostLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'costLines';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentCurrencyDollar;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('catalog.relations.cost_lines.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return CatalogItemResource::enabled()
            && FeatureFlags::enabled(Feature::CostItems)
            && Gate::allows('viewAny', CostListLine::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation | Builder
    {
        /** @var CatalogItem $item */
        $item = $this->getOwnerRecord();

        return app(CostListQueries::class)->linesForCatalogItem($item);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('catalog.relations.cost_lines.title'))
            ->columns([
                // Teklifin adi; altinda TKLF ve POTIS kodu (CodeLine, 14 Ekim 2026).
                CodeLine::under(
                    TextColumn::make('costList.proposal.title')
                        ->label(__('catalog.relations.cost_lines.proposal'))
                        ->limit(30)
                        ->placeholder('-'),
                    fn (CostListLine $record): ?string => self::proposal($record)?->proposal_no,
                    fn (CostListLine $record): ?string => self::proposal($record)?->businessCase?->caseCode()?->formatted_code,
                ),
                TextColumn::make('project')
                    ->label(__('catalog.relations.cost_lines.project'))
                    ->state(fn (CostListLine $record): ?string => self::project($record)?->getAttribute('display_name'))
                    ->url(fn (CostListLine $record): ?string => ($project = self::project($record)) !== null ? RecordLinks::detailUrl($project, checkRecord: false) : null)
                    ->icon(fn (CostListLine $record): mixed => ($project = self::project($record)) !== null ? RecordLinks::iconFor($project) : null)
                    ->placeholder('-')
                    ->limit(40),
                TextColumn::make('description')
                    ->label(__('cost_items.fields.description'))
                    ->description(fn (CostListLine $record): ?string => filled($record->path()) ? $record->path() : null)
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label(__('catalog.relations.cost_lines.quantity'))
                    ->formatStateUsing(fn (mixed $state, CostListLine $record): string => trim((Money::number($state, 2) ?? '-').' '.($record->getAttribute('unit') ?? '')))
                    ->placeholder('-')
                    ->alignEnd(),
                MoneyDisplay::column('unit_cost', fn (?Model $record): mixed => self::currency($record), 4)
                    ->label(__('catalog.relations.cost_lines.unit_cost')),
                MoneyDisplay::column('total_cost', fn (?Model $record): mixed => self::currency($record))
                    ->label(__('catalog.relations.cost_lines.total_cost')),
                TextColumn::make('price_state')
                    ->label(__('catalog.relations.cost_lines.price_state'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('purchaseNeed.status')
                    ->label(__('catalog.relations.cost_lines.purchase_status'))
                    ->badge()
                    ->placeholder(__('catalog.relations.cost_lines.not_sent')),
            ])
            // Satir teklifin sayfasini acar (maliyet satirinin kendi sayfasi yok).
            ->recordUrl(fn (CostListLine $record): ?string => ($proposal = self::proposal($record)) !== null ? RecordLinks::detailUrl($proposal) : null)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('catalog.relations.cost_lines.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentCurrencyDollar)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    private static function proposal(CostListLine $line): ?Proposal
    {
        $proposal = $line->costList?->proposal;

        return $proposal instanceof Proposal ? $proposal : null;
    }

    /** Siparis kalemindeki proje, yoksa teklifin potansiyel isinin projesi. */
    private static function project(CostListLine $line): ?Project
    {
        $project = $line->purchaseNeed?->project;

        if ($project instanceof Project) {
            return $project;
        }

        $case = self::proposal($line)?->businessCase;
        $project = $case instanceof BusinessCase ? $case->project : null;

        return $project instanceof Project ? $project : null;
    }

    private static function currency(?Model $record): mixed
    {
        return $record instanceof CostListLine
            ? ($record->getAttribute('currency_code') ?: $record->costList?->getAttribute('currency_code'))
            : null;
    }
}
