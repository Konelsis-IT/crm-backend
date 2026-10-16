<?php

declare(strict_types=1);

namespace App\Filament\Widgets\UiLab;

use App\Filament\Pages\UiNewDashboard;
use App\Query\Ui\NewDashboardQueries;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * UI Deneme > Yeni pano taslagi (D-207): panonun altindaki istatistik
 * (notlar: "Istatistik asagida olsun"). $kind = acquisition | purchasing.
 */
class NewDashboardStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public string $kind = 'acquisition';

    public static function canView(): bool
    {
        return UiNewDashboard::canAccess();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $queries = app(NewDashboardQueries::class);

        if ($this->kind === 'purchasing') {
            return [
                $this->stat('pur_entry', $queries->openPurchaseRequests()->count() + $queries->needsInEntry()->count(), Heroicon::OutlinedPencilSquare, 'orange'),
                $this->stat('pur_projects', $queries->projectsWithOpenPurchasing()->count(), Heroicon::OutlinedBriefcase, 'info'),
                $this->stat('pur_orders', $queries->pendingOrders()->count(), Heroicon::OutlinedClock, 'orange'),
                $this->stat('pur_production', $queries->ordersInProduction()->count(), Heroicon::OutlinedTruck, 'info'),
            ];
        }

        $stats = $queries->acquisitionStats();
        $concluded = $stats['won'] + $stats['lost'];

        return [
            $this->stat('to_submit', $stats['to_submit'], Heroicon::OutlinedClipboardDocumentList, 'orange'),
            $this->stat('awaiting', $stats['awaiting'], Heroicon::OutlinedPaperAirplane, 'info')
                ->description($stats['oldest_waiting_days'] !== null ? __('new_dashboard.stats.oldest', ['days' => $stats['oldest_waiting_days']]) : null),
            $this->stat('won', $stats['won'], Heroicon::OutlinedTrophy, 'success'),
            $this->stat('lost', $stats['lost'], Heroicon::OutlinedXCircle, 'gray'),
            $this->stat('win_rate', $concluded > 0 ? '%'.round($stats['won'] * 100 / $concluded) : '–', Heroicon::OutlinedChartPie, 'success')
                ->description(__('new_dashboard.stats.win_rate_help', ['count' => $concluded])),
        ];
    }

    private function stat(string $key, int | string $value, Heroicon $icon, string $tone): Stat
    {
        return Stat::make(__('new_dashboard.stats.'.$key), $value)
            ->icon($icon)
            ->extraAttributes(['class' => 'kc-stat kc-stat-'.$tone]);
    }
}
