<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Filament\Pages\Work\WorkBoard;
use App\Filament\Resources\WorkItems\Tables\WorkItemTable;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use App\Support\WorkDurationFormat;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Personel karti > Isler sekmesi (B36, D-115; Filament tablosu): Isler
 * listesinin kisiye suzulmus hali. Rozet acik kart sayisidir; ustte kisinin
 * bu haftaki sayilari. Kisinin kendisi, amiri ve tum kartlari goren yetkili gorur.
 */
class WorkItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'workItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_item.personnel_tab.title');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! SchemaReadiness::hasBatch('B36')) {
            return null;
        }

        $count = app(WorkItemQueries::class)->openCount((int) $ownerRecord->getKey());

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return SchemaReadiness::hasBatch('B36')
            && $user instanceof Personnel
            && $ownerRecord instanceof Personnel
            && Gate::forUser($user)->allows('viewPersonnelItems', [WorkItem::class, $ownerRecord]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Personnel $owner */
        $owner = $this->getOwnerRecord();
        $week = app(WorkItemQueries::class)->personnelWeek((int) $owner->getKey());

        return WorkItemTable::make($table, forPersonnel: true)
            ->heading(__('work_item.personnel_tab.heading'))
            ->description(__('work_item.personnel_tab.kpis', [
                'done' => $week['done'],
                'open' => $week['open'],
                'waiting' => $week['waiting'],
                'hours' => WorkDurationFormat::hours($week['hours']) ?? '0',
                'closed' => $week['closed_days'],
                'workdays' => $week['workdays'],
            ]))
            ->headerActions([
                Action::make('board')
                    ->label(__('work_item.actions.open_board'))
                    ->icon(Heroicon::OutlinedViewColumns)
                    ->color('gray')
                    ->url(fn (): string => WorkBoard::getUrl(['tip' => 'ekip'])),
                Action::make('list')
                    ->label(__('work_item.actions.open_list'))
                    ->icon(Heroicon::OutlinedQueueList)
                    ->color('gray')
                    ->url(fn (): string => WorkItemResource::getUrl('index')),
            ]);
    }
}
