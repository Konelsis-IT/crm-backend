<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Models\Personnel\Personnel;
use App\Services\Authorization\RoleResolver;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * UI Deneme (10 Eylul 2026): arayuz bilesenlerinin kalici katalogu. Kullanici
 * adaylari burada yan yana karsilastirip secer; buraya eklenen hicbir sey
 * silinmez, yeni varyantlar yanina eklenir. Yalniz sistem yoneticisi gorur.
 * Kume icindeki sayfalar ust sekmelerde: kart tasarimi, eklenti kartlari, ...
 */
class UiGallery extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 900;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.nav');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('ui_gallery.nav');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof Personnel && app(RoleResolver::class)->hasFullAccess($user);
    }
}
