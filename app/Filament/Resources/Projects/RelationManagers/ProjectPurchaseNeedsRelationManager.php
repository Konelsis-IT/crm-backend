<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\UsageArea;
use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedActions;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedColumns;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedResource;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Query\Project\ProjectPurchasingQueries;
use App\Support\Projects\ProjectPurchasing;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Proje sayfasinda Satin Alma adiminin "Satın alma" tablosu (D-196; eski
 * "Tedarik kalemleri" tablosunun yerine, anahtar ProjectPurchasing): projenin
 * Satin alinacaklar'i - kalem (ad, altinda kategori ya da "Listede yok"),
 * kaynak, miktar ve birim, durum, bagli satin alma teklifi ve siparis
 * (Satin alinacaklar listesiyle ayni sutunlar), termin. Satira tiklamak
 * kalemin sayfasini acar (TableConventions). "Satın alma talebi aç" mevcut
 * talep formunu Saha ve bu proje secili acar; kontrol listesindeki "+" da
 * bu dugmeyi acar (OpensFromChecklist, eylem adi "create").
 */
class ProjectPurchaseNeedsRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'purchaseNeeds';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_supply_item.purchasing.needs_title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ProjectPurchasing::enabled() && Gate::allows('viewAny', PurchaseNeed::class);
    }

    /** Alt sinif (Yazilim) tabloyu daraltir. */
    protected function scopeNeeds(Builder $query): Builder
    {
        return $query;
    }

    protected function heading(): string
    {
        return __('project_supply_item.purchasing.needs_title');
    }

    protected function emptyHeading(): string
    {
        return __('project_supply_item.purchasing.needs_empty');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->heading())
            ->modelLabel(__('purchasing.label'))
            ->pluralModelLabel(__('purchasing.plural'))
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->scopeNeeds(
                app(ProjectPurchasingQueries::class)->needsTable($query, $this->viewer()),
            ))
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
                TextColumn::make('source')
                    ->label(__('purchasing.fields.source'))
                    ->badge(),
                TextColumn::make('quantity')
                    ->label(__('purchasing.fields.quantity'))
                    ->formatStateUsing(fn (mixed $state, PurchaseNeed $record): string => PurchaseNeedResource::quantityText($record))
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('purchasing.fields.status'))
                    ->badge(),
                PurchaseNeedColumns::quotation(),
                PurchaseNeedColumns::order(),
                TextColumn::make('needed_by')
                    ->label(__('purchasing.fields.needed_by'))
                    ->date('d.m.Y')
                    ->color(fn (PurchaseNeed $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn (PurchaseNeed $record): ?Heroicon => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('purchasing.fields.status'))
                    ->options(PurchaseNeedStatus::class)
                    ->native(false),
                SelectFilter::make('source')
                    ->label(__('purchasing.fields.source'))
                    ->options(PurchaseNeedSource::class)
                    ->native(false),
            ])
            ->headerActions([$this->requestAction()])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading($this->emptyHeading())
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * Mevcut "Satın alma talebi aç" (kalemli talep formu; kapaliysa tek
     * kalemli eski form, PurchaseNeedActions::createNeed): kullanim yeri Saha
     * ve proje bu proje secili gelir. Gorunurluk ve kayit o eylemdedir.
     */
    private function requestAction(): Action
    {
        $projectId = (int) $this->getOwnerRecord()->getKey();

        return PurchaseNeedActions::createNeed(false)
            ->name('create')
            ->mountUsing(static function (?Schema $schema) use ($projectId): void {
                if ($schema === null) {
                    return;
                }

                $schema->fill();
                $schema->fillPartially(
                    ['usage_area' => UsageArea::Field->value, 'project_id' => $projectId],
                    ['usage_area', 'project_id'],
                );
            });
    }

    private function viewer(): ?Personnel
    {
        $user = auth()->user();

        return $user instanceof Personnel ? $user : null;
    }
}
