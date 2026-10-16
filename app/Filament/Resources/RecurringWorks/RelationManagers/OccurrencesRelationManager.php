<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringWorks\RelationManagers;

use App\Enums\Platform\Feature;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\CodeLine;
use App\Models\Report\RecurringWork;
use App\Models\Report\WorkItem;
use App\Query\Report\RecurringWorkQueries;
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
 * Gorevin donem isleri (D-219): her donem acilan is, durumu, son tarihi ve
 * uyumu (zamaninda / gec / yapilmadi / suruyor). Salt okunur; satira tiklayinca
 * is detayi acilir.
 */
class OccurrencesRelationManager extends RelationManager
{
    protected static string $relationship = 'occurrences';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCalendarDays;

    /** @var array<int, string>|null */
    private ?array $states = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return FeatureFlags::enabled(Feature::WorkRecurring)
            && SchemaReadiness::hasBatch('B57')
            && (auth()->user()?->can('view', $ownerRecord) ?? false);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('recurring_work.occurrences.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('recurring_work.occurrences.title'))
            ->description(__('recurring_work.occurrences.help'))
            ->columns([
                CodeLine::under(
                    TextColumn::make('title')
                        ->label(__('work_item.table.item'))
                        ->weight('medium')
                        ->wrap(),
                    fn (WorkItem $record): ?string => $record->getAttribute('item_no'),
                ),
                TextColumn::make('period_key')
                    ->label(__('recurring_work.occurrences.period'))
                    ->fontFamily('mono'),
                TextColumn::make('status')
                    ->label(__('work_item.fields.status'))
                    ->badge(),
                TextColumn::make('due_on')
                    ->label(__('work_item.fields.due_on'))
                    ->date('d.m.Y')
                    ->placeholder('–'),
                TextColumn::make('done_on')
                    ->label(__('recurring_work.occurrences.done_on'))
                    ->date('d.m.Y')
                    ->placeholder('–'),
                TextColumn::make('compliance')
                    ->label(__('recurring_work.fields.compliance'))
                    ->state(fn (WorkItem $record): string => __('recurring_work.states.'.$this->stateOf($record)))
                    ->badge()
                    ->color(fn (WorkItem $record): string => match ($this->stateOf($record)) {
                        RecurringWorkQueries::ON_TIME => 'success',
                        RecurringWorkQueries::LATE => 'warning',
                        RecurringWorkQueries::MISSED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordUrl(fn (WorkItem $record): ?string => Gate::allows('view', $record) ? WorkItemResource::getUrl('view', ['record' => $record]) : null)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('work_on')->orderByDesc('id'))
            ->paginated([10, 25, 50])
            ->emptyStateHeading(__('recurring_work.occurrences.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }

    private function stateOf(WorkItem $record): string
    {
        if ($this->states === null) {
            $owner = $this->getOwnerRecord();
            $id = $owner instanceof RecurringWork ? (int) $owner->getKey() : 0;
            $this->states = [];

            foreach (app(RecurringWorkQueries::class)->compliance([$id], PHP_INT_MAX)[$id] ?? [] as $row) {
                $this->states[$row['item']] = $row['state'];
            }
        }

        return $this->states[(int) $record->getKey()] ?? RecurringWorkQueries::OPEN;
    }
}
