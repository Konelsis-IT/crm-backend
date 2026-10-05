<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Platform\Feature;
use App\Models\Personnel\Personnel;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Genel bakis basligi yanindaki sayilar (D-146): Bugün, Geciken, Yaklaşan
 * (yarinin kartlari); "Görevlerim ve işlerim" tablosuyla ayni sayilar, yalniz
 * kisinin kendi kartlari (D-147). Renkler (D-147, 30 Eylul 2026 kullanici
 * istegi): Bugün mavi, Geciken kirmizi, Yaklaşan turuncu (konelsis.css).
 */
class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return FeatureFlags::enabled(Feature::DashboardStats)
            && FeatureFlags::enabled(Feature::WorkItems)
            && SchemaReadiness::hasBatch('B36')
            && auth()->user() instanceof Personnel;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $me = auth()->user();
        $counts = $me instanceof Personnel
            ? app(WorkItemQueries::class)->agendaCounts($me)
            : ['overdue' => 0, 'today' => 0, 'tomorrow' => 0];

        return [
            Stat::make(__('work_item.dashboard.stats.today'), $counts['today'])
                ->icon(Heroicon::OutlinedCalendarDays)
                ->extraAttributes(['class' => 'kc-stat kc-stat-info']),
            Stat::make(__('work_item.dashboard.stats.overdue'), $counts['overdue'])
                ->icon(Heroicon::OutlinedClock)
                ->extraAttributes(['class' => 'kc-stat kc-stat-danger']),
            Stat::make(__('work_item.dashboard.stats.upcoming'), $counts['tomorrow'])
                ->icon(Heroicon::OutlinedDocumentText)
                ->extraAttributes(['class' => 'kc-stat kc-stat-orange']),
        ];
    }
}
