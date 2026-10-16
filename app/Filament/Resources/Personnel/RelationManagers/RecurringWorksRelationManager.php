<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Enums\Platform\Feature;
use App\Filament\Resources\RecurringWorks\RecurringWorkResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\RecurringWork;
use App\Models\Report\WorkItem;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Personel karti > Gorev ve sorumluluklar (D-219): kisinin tekrarli isleri ve
 * son donemlerin uyumu. Kisinin kendisi, amiri ve ust yonetim gorur; tanimi
 * Gorev ve sorumluluklar ekranindan yapilir.
 */
class RecurringWorksRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringWorks';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArrowPath;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return FeatureFlags::enabled(Feature::WorkRecurring)
            && SchemaReadiness::hasBatch('B57')
            && $user instanceof Personnel
            && $ownerRecord instanceof Personnel
            && ($user->is($ownerRecord) || Gate::forUser($user)->allows('viewPersonnelItems', [WorkItem::class, $ownerRecord]));
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('recurring_work.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('recurring_work.plural'))
            ->description(__('recurring_work.help.compliance'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('recurring_work.fields.title'))
                    ->description(fn (RecurringWork $record): ?string => $record->kind?->getLabel())
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('frequency')
                    ->label(__('recurring_work.fields.frequency'))
                    ->badge()
                    ->description(fn (RecurringWork $record): ?string => RecurringWorkResource::dayText($record)),
                TextColumn::make('compliance')
                    ->label(__('recurring_work.fields.compliance'))
                    ->state(fn (RecurringWork $record): string => RecurringWorkResource::complianceText($record))
                    ->color(fn (RecurringWork $record): string => RecurringWorkResource::complianceColor($record)),
                IconColumn::make('is_active')
                    ->label(__('recurring_work.fields.is_active'))
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('archived_at'))
            ->recordUrl(fn (RecurringWork $record): ?string => Gate::allows('view', $record) ? RecurringWorkResource::getUrl('view', ['record' => $record]) : null)
            ->headerActions([
                Action::make('open_list')
                    ->label(__('recurring_work.actions.open_list'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (): string => RecurringWorkResource::getUrl('index')),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('title')
            ->paginated(false)
            ->emptyStateHeading(__('recurring_work.empty_person'))
            ->emptyStateIcon(Heroicon::OutlinedArrowPath);
    }
}
