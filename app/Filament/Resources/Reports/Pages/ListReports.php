<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Exports\ReportExporter;
use App\Filament\Resources\Reports\Concerns\CopiesReportText;
use App\Filament\Resources\Reports\ReportActions;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Support\ExportActions;
use App\Models\Personnel\Personnel;
use App\Query\Report\ReportQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rapor listesi: Raporlarim / Inceleme kutum / Ekibim / Tumu (gorebildiklerim).
 * D-167: satirda Kopyala ve rapora ozel PDF / Excel (ReportActions).
 */
class ListReports extends ListRecords
{
    use CopiesReportText;

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
        // "Bugunun raporu" dugmesi kaldirildi (24 Eylul 2026 kullanici istegi):
        // gunluk rapor Is panosundaki "Gunluk rapora donustur" ile uretilir.
        return [
            // D-167: raporlar kendi disa aktarim anahtarlariyla (genel Excel kapali kalir).
            ExportActions::table(ReportExporter::class, ReportActions::switches()),
            CreateAction::make()->label(__('report.actions.create')),
        ];
    }

    /**
     * D-147 (30 Eylul 2026 kullanici karari): "Ekibim" yalniz altinda
     * personel olanlarda (ekip muduru) ve ust yonetimde, "Tümü" yalniz ust
     * yonetimde (Yonetim kurulu baskani, Idari mudur) gorunur.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $queries = app(ReportQueries::class);
        $user = auth()->user();
        $personnelId = (int) auth()->id();
        $seesAll = $user instanceof Personnel && $queries->seesAllReports($user);

        return [
            'mine' => Tab::make(__('report.tabs.mine'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyMine($query, $personnelId)),
            'review' => Tab::make(__('report.tabs.review'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->badge(fn (): ?string => ($count = $queries->reviewInboxCount($personnelId)) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyReviewInbox($query, $personnelId)),
            ...($seesAll || $queries->hasTeam($personnelId) ? [
                'team' => Tab::make(__('report.tabs.team'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyTeam($query, $personnelId)),
            ] : []),
            ...($seesAll ? [
                'all' => Tab::make(__('report.tabs.all'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyVisible($query, $user)),
            ] : []),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'mine';
    }
}
