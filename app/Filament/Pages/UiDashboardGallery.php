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
 * UI Deneme > Departman panolari (D-173, 8 Ekim 2026 kullanici istegi:
 * "tamamen React ile"; siki, borsa ekrani gibi yogun pano).
 *
 * Ustteki departman seciciyle Genel / Teklif - Is Gelistirme / Yonetici
 * panolari arasinda gecilir; ekran departmana gore yeniden dizilir. Varsayilan
 * kip kisinin kendi panosudur (DepartmentDashboards::modeFor). Veri gercektir,
 * salt okunur; durum dugmeleri kayit degistirmez (deneme). Filament'in
 * tablo / widget bilesenleri borsa tipi birlesik grafik, hucre ici cizgi
 * grafik ve satir arkasi cubuklari veremedigi icin ekran React'tir
 * (resources/js/dashboards, konelsis-dash.css). Katalogun parcasidir; silinmez.
 */
class UiDashboardGallery extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'departman-panolari';

    protected string $view = 'filament.dashboards.app';

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.dashboards');
    }

    public function getTitle(): string | Htmlable
    {
        return __('ui_gallery.pages.dashboards');
    }

    /** Basligi ve departman seciciyi React cizer. */
    public function getHeading(): string | Htmlable
    {
        return '';
    }

    /**
     * Calisma arayuzu (D-91 / D-173): gelistirme ortami, ozellik acik, tam yetkili kisi.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return DepartmentDashboards::canPreview($user instanceof Personnel ? $user : null, Feature::UiDashboards);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $mode = request()->query('pano');

        return ['root' => 'dash-app', 'config' => DashboardAppConfig::make('dash-app', is_string($mode) ? $mode : null)];
    }
}
