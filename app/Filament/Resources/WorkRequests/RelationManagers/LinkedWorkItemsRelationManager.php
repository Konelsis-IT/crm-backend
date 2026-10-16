<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\RelationManagers;

use App\Enums\Platform\Feature;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\CodeLine;
use App\Models\Report\WorkItem;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Talebe bagli isler (D-216; kullanici 15 Ekim 2026: "Talep sayfasında bağlı
 * işler ve durumları görünsün"). Talep atanınca kendiliginden acilan is ve
 * elle talebe baglanan isler: sorumlu, durum, son tarih, tamamlanma. Salt
 * okunur; isi gorebilen satira tiklayinca is detayina gider.
 */
class LinkedWorkItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'workItems';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedRectangleStack;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return FeatureFlags::enabled(Feature::WorkRequestWorkItems)
            && FeatureFlags::enabled(Feature::WorkItems)
            && SchemaReadiness::hasBatch('B36')
            && (auth()->user()?->can('view', $ownerRecord) ?? false);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_request.sections.work_items');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('work_request.sections.work_items'))
            ->description(__('work_request.help.work_items'))
            ->columns([
                CodeLine::under(
                    TextColumn::make('title')
                        ->label(__('work_item.table.item'))
                        ->weight('medium')
                        ->wrap(),
                    fn (WorkItem $record): ?string => $record->getAttribute('item_no'),
                ),
                TextColumn::make('personnel.full_name')
                    ->label(__('work_item.fields.personnel'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->placeholder('–'),
                TextColumn::make('status')
                    ->label(__('work_item.fields.status'))
                    ->badge(),
                TextColumn::make('due_on')
                    ->label(__('work_item.fields.due_on'))
                    ->date('d.m.Y')
                    ->placeholder('–'),
                TextColumn::make('completed_at')
                    ->label(__('work_request.fields.work_completed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('–'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('personnel:id,full_name'))
            ->recordUrl(fn (WorkItem $record): ?string => Gate::allows('view', $record) ? WorkItemResource::getUrl('view', ['record' => $record]) : null)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->emptyStateHeading(__('work_request.values.no_work_items'))
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack);
    }
}
