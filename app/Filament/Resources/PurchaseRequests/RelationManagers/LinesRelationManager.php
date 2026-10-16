<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\RelationManagers;

use App\Filament\Resources\PurchaseNeeds\PurchaseNeedColumns;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedResource;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestForm;
use App\Models\Procurement\PurchaseNeed;
use App\Query\Procurement\PurchaseRequestQueries;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Talebin kalemleri (D-192, B53): sira, kalem (listedeki malzeme / hizmet ya
 * da "Listede yok"), miktar, durum, girdigi satin alma teklifleri ve siparis.
 * Salt okunur; satira tiklamak kalemin Satin alinacaklar sayfasini acar
 * (TableConventions). Kalemler talep duzenlenerek degisir.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'needs';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedListBullet;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('purchase_request.sections.lines');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PurchaseRequestForm::enabled() && PurchaseNeedResource::canAccess();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('purchase_request.sections.lines'))
            ->modifyQueryUsing(fn (Builder $query): Builder => app(PurchaseRequestQueries::class)->withLineRelations($query))
            ->columns([
                TextColumn::make('sort_order')
                    ->label(__('purchase_request.lines.no'))
                    ->formatStateUsing(fn (mixed $state): string => (string) ((int) $state + 1))
                    ->alignCenter(),
                TextColumn::make('title')
                    ->label(__('purchase_request.lines.item'))
                    ->description(fn (PurchaseNeed $record): ?string => $record->hasCatalogItem()
                        ? $record->catalogItem?->category?->path()
                        : __('purchasing.values.not_in_catalog'))
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->hasCatalogItem() ? $record->catalogItem?->kindEnum()->getIcon() : Heroicon::OutlinedQuestionMarkCircle)
                    ->iconColor(fn (PurchaseNeed $record): ?string => $record->hasCatalogItem() ? $record->catalogItem?->kindEnum()->getColor() : 'warning')
                    ->wrap()
                    ->limit(80),
                TextColumn::make('brand_model')
                    ->label(__('purchase_request.fields.brand_model'))
                    ->placeholder('-')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('quantity')
                    ->label(__('purchase_request.lines.quantity'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => PurchaseNeedResource::quantityText($record))
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('purchase_request.lines.status'))
                    ->badge(),
                PurchaseNeedColumns::quotation(),
                PurchaseNeedColumns::order(),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('purchase_request.lines.empty'))
            ->paginated(false);
    }
}
