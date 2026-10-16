<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\RelationManagers;

use App\Enums\Platform\Feature;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Support\CodeLine;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Query\Procurement\PurchaseNeedQueries;
use App\Services\Platform\FeatureFlags;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
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
 * Katalog kalemi sayfasinda "Siparis kalemleri" (D-189): bu kalem icin acilan
 * siparis kalemleri; durum, teklif, proje, miktar, satin almanin onerdigi
 * teklif ve termin. Salt okunur; satira tiklamak siparis kaleminin sayfasini
 * acar (TableConventions).
 */
class PurchaseNeedsRelationManager extends RelationManager
{
    protected static string $relationship = 'needs';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedShoppingBag;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('catalog.relations.needs.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return CatalogItemResource::enabled()
            && FeatureFlags::enabled(Feature::PurchaseNeeds)
            && Gate::allows('viewAny', PurchaseNeed::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation | Builder
    {
        /** @var CatalogItem $item */
        $item = $this->getOwnerRecord();

        return app(PurchaseNeedQueries::class)->forCatalogItem($item);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('catalog.relations.needs.title'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('catalog.relations.needs.need'))
                    ->wrap()
                    ->limit(80),
                TextColumn::make('status')
                    ->label(__('catalog.relations.needs.status'))
                    ->badge(),
                // Teklifin adi; altinda TKLF kodu (CodeLine, 14 Ekim 2026).
                CodeLine::under(
                    TextColumn::make('proposal.title')
                        ->label(__('catalog.relations.needs.proposal'))
                        ->limit(30)
                        ->placeholder('-'),
                    'proposal.proposal_no',
                ),
                TextColumn::make('project.name')
                    ->label(__('catalog.relations.needs.project'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => (string) ($record->project?->getAttribute('display_name') ?? $state))
                    ->placeholder('-')
                    ->limit(40),
                TextColumn::make('quantity')
                    ->label(__('catalog.relations.needs.quantity'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => trim((Money::number($state, 2) ?? '-').' '.(app(UnitResolver::class)->display($record) ?? '')))
                    ->alignEnd(),
                TextColumn::make('recommended_quote')
                    ->label(__('catalog.relations.needs.recommended'))
                    ->state(fn (PurchaseNeed $record): ?string => self::recommended($record))
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('needed_by')
                    ->label(__('catalog.relations.needs.needed_by'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->color(fn (PurchaseNeed $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('catalog.relations.needs.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShoppingBag)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /** Onerilen teklif: "Tedarikci · 1.000,50 [simge]". */
    private static function recommended(PurchaseNeed $need): ?string
    {
        $quote = $need->recommendedQuote;

        if (! $quote instanceof PurchaseQuote) {
            return null;
        }

        return $quote->supplierLabel().' · '.Money::format($quote->getAttribute('total_amount'), $quote->getAttribute('currency_code'));
    }
}
