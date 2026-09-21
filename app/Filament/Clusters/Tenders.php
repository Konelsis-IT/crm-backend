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
 * Is Alim > Ihaleler kumesi (21 Eylul 2026 kullanici istegi): ihale ilanlari
 * ve ihale kaynaklari ust sekmelerde. Ihale kaynaklari once Ayarlar
 * kumesindeydi; ilanlarla birlikte kullanildigi icin buraya tasindi.
 */
class Tenders extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 30;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('app.nav.tenders');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('app.nav.tenders');
    }
}
