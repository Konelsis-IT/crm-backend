<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Filament\NavigationGroup;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Analizler > Is raporlari (B36, D-115; menu D-116): panodan bagimsiz ayri
 * menu. Ust sekmelerde Analiz panosu (React) ve Sure raporu (Filament
 * tablosu). 23 Eylul 2026 kullanici karari: Analizler yalniz ust yonetimindir.
 */
class WorkReports extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Analytics;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'is-raporlari';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('work_item.nav.reports');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('work_item.nav.reports');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return SchemaReadiness::hasBatch('B36')
            && $user instanceof Personnel
            && Gate::forUser($user)->allows('viewAnalytics', WorkItem::class);
    }
}
