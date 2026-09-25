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
 * Ayarlar kumesi. 24 Eylul 2026 kullanici istegi: sol menude yoktur; sag
 * ustteki kullanici menusundeki "Ayarlar" ile acilir (AdminPanelProvider).
 * Alt sayfalar ust sekmelerde kalir.
 */
class Settings extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static bool $shouldRegisterNavigation = false;

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
