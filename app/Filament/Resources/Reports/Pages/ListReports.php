<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Exports\ReportExporter;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Support\ExportActions;
use App\Models\Personnel\Personnel;
use App\Query\Report\ReportQueries;
use App\Reports\Templates\DailyWorkReportTemplate;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Rapor listesi: Raporlarim / Inceleme kutum / Ekibim / Tumu (gorebildiklerim).
 * "Bugunun raporu" kisayolu gunluk rapor yoksa gorunur.
 */
class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    public function getSubheading(): ?string
    {
        return __('report.help.list');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $today = (new DailyWorkReportTemplate)->code();
        $me = (int) auth()->id();

        return [
            Action::make('today')
                ->label(__('report.actions.today'))
                ->icon(Heroicon::OutlinedSun)
                ->color('gray')
                ->url(ReportResource::getUrl('create', [CreateReport::QUERY_TEMPLATE => $today]))
                ->visible(fn (): bool => $me > 0 && ! app(ReportQueries::class)->hasReportForDay($today, $me, Carbon::today(DisplayTime::zone()))),
            ExportActions::table(ReportExporter::class),
            CreateAction::make()->label(__('report.actions.create')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $queries = app(ReportQueries::class);
        $user = auth()->user();
        $personnelId = (int) auth()->id();

        return [
            'mine' => Tab::make(__('report.tabs.mine'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyMine($query, $personnelId)),
            'review' => Tab::make(__('report.tabs.review'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->badge(fn (): ?string => ($count = $queries->reviewInboxCount($personnelId)) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyReviewInbox($query, $personnelId)),
            'team' => Tab::make(__('report.tabs.team'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyTeam($query, $personnelId)),
            'all' => Tab::make(__('report.tabs.all'))
                ->icon(Heroicon::OutlinedRectangleStack)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyVisible($query, $user instanceof Personnel ? $user : null)),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'mine';
    }
}
