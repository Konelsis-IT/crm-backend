<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans\Pages;

use App\Filament\Exports\MeetingPlanExporter;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Support\ExportActions;
use App\Query\Party\MeetingPlanQueries;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gorusme plani listesi (B34, D-109): sekmeler yaklasan / bugun / geciken /
 * gerceklesen / gerceklesmeyen / tumu; takvim ana sayfadir.
 */
class ListMeetingPlans extends ListRecords
{
    protected static string $resource = MeetingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label(__('meeting_plan.actions.calendar'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(MeetingPlanResource::getUrl('index')),
            ExportActions::table(MeetingPlanExporter::class),
            CreateAction::make()->label(__('meeting_plan.actions.create')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = app(MeetingPlanQueries::class)->tabCounts();
        $tabs = [];

        foreach (['upcoming', 'today', 'overdue', 'done', 'cancelled', 'all'] as $tab) {
            $tabs[$tab] = Tab::make(__('meeting_plan.tabs.'.$tab))
                ->badge($counts[$tab] ?? null)
                ->badgeColor($tab === 'overdue' ? 'danger' : 'gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => app(MeetingPlanQueries::class)->tab($query, $tab));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'upcoming';
    }
}
