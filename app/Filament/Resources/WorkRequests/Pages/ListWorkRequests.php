<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\Pages;

use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Query\WorkRequest\WorkRequestQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListWorkRequests extends ListRecords
{
    protected static string $resource = WorkRequestResource::class;

    public function getSubheading(): ?string
    {
        return __('work_request.help.list');
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('work_request.actions.create')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $queries = app(WorkRequestQueries::class);
        $personnelId = (int) auth()->id();

        return [
            'inbox' => Tab::make(__('work_request.tabs.inbox'))
                ->icon(Heroicon::OutlinedInbox)
                ->badge(fn (): ?string => ($count = $queries->inboxCount($personnelId)) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyInbox($query, $personnelId)),
            'mine' => Tab::make(__('work_request.tabs.mine'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyMine($query, $personnelId)),
            'open' => Tab::make(__('work_request.tabs.open'))
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyOpen($query)),
            'all' => Tab::make(__('work_request.tabs.all'))
                ->icon(Heroicon::OutlinedRectangleStack),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'inbox';
    }
}
