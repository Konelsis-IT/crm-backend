<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Genel bakis: bugun ve yarin yapilacak islerim (kullanici istegi,
 * 23 Eylul 2026). Kisinin kendi kartlari; bugunun ve yarinin kartlari ile
 * onceki gunlerden devreden acik kartlar. Kart girisi Is panosunda yapilir;
 * bu tablo yalniz gosterir ve karta baglanir.
 */
class MyWorkItemsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SchemaReadiness::hasBatch('B36') && auth()->user() instanceof Personnel;
    }

    public function table(Table $table): Table
    {
        $me = auth()->user();
        $personnelId = $me instanceof Personnel ? (int) $me->getKey() : 0;
        $today = WorkItemQueries::today();
        $tomorrow = $today->copy()->addDay();

        return $table
            ->heading(__('work_item.widget.heading'))
            ->description(__('work_item.widget.description', [
                'today' => $today->format('d.m'),
                'tomorrow' => $tomorrow->format('d.m'),
            ]))
            ->query(fn (): Builder => app(WorkItemQueries::class)->personnelRange($personnelId, $today, $tomorrow))
            ->defaultSort('work_at')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (WorkItem $record): ?string => WorkItemResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('work_item.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->columns([
                TextColumn::make('work_at')
                    ->label(__('work_item.table.date'))
                    ->dateTime('d.m H:i')
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('work_item.table.item'))
                    ->description(fn (WorkItem $record): ?string => $record->project?->name)
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('work_item.table.status'))
                    ->badge(),
                TextColumn::make('due_on')
                    ->label(__('work_item.fields.due_on'))
                    ->date('d.m.Y')
                    ->placeholder('–')
                    ->toggleable(),
            ]);
    }
}
