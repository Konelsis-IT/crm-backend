<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Clusters\UiGallery;
use App\Filament\Support\DashboardAppConfig;
use App\Models\Personnel\Personnel;
use App\Support\Dashboards\DepartmentDashboards;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * UI Deneme > Pano bilesenleri (D-173): departman panolarina konabilecek
 * numarali bilesen secenekleri (gosterge bandi, birlesik grafikler, yogun
 * teklif tablolari, durum dugmeleri, huni, isi haritasi, firma liderlik
 * tablosu, mini halka, uyari seridi, hareket akisi...). Kullanici "3 ve 7'yi
 * kullan" diyerek secer. Ayni React cekirdegi ve ayni gercek veri; durum
 * dugmeleri kayit degistirmez. Katalogun parcasidir; silinmez.
 */
class UiDashboardComponents extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'pano-bilesenleri';

    protected string $view = 'filament.dashboards.app';

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.dashboard_components');
    }

    public function getTitle(): string | Htmlable
    {
        return __('ui_gallery.pages.dashboard_components');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return DepartmentDashboards::canPreview($user instanceof Personnel ? $user : null, Feature::UiDashboardComponents);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['root' => 'dash-catalog', 'config' => DashboardAppConfig::make('dash-catalog')];
    }
}
