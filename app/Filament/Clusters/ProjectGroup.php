<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Filament\NavigationGroup;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Operasyon > Proje Grubu departman kumesi (D-70): projeler ve projeye bagli
 * alt kaynaklar (workstream, is paketi, WBS, gecikme, onay kapisi, departman
 * devri). Menude Operasyon grubunun ilk ogesidir; ekranlar ust sekmelerde.
 */
class ProjectGroup extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 10;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('app.nav.project_group');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('app.nav.project_group');
    }
}
