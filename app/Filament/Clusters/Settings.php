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
 *
 */
class Settings extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('app.nav.settings');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('app.nav.settings');
    }
}
