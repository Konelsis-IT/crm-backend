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
 * Operasyon > Satin Alma departman kumesi (D-70): satin alma masasinin
 * ekranlari (bugun Tedarik Kalemleri; ileride talep, teklif toplama,
 * siparis). Menude Operasyon grubunun ikinci ogesidir; ekranlar ust sekmelerde.
 */
class Procurement extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 20;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('app.nav.procurement');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('app.nav.procurement');
    }
}
