<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Clusters\UiGallery;
use App\Filament\Support\CostLabConfig;
use App\Models\Personnel\Personnel;
use App\Support\Dashboards\DepartmentDashboards;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * UI Deneme > Maliyet kalemleri (D-187, 9 Ekim 2026 kullanici istegi).
 *
 * Excel maliyet listesinin (Kartal RES Hibrit GES 38,8 MWp) gercek kalemleriyle
 * numarali tasarim secenekleri: Urun/Hizmet | Idari Kadro | Genel Giderler
 * sekmeleri, kategorileri bozmayan sik onay listesi, kalem basina departman
 * onaylari, katalog eslestirmesi (Excel yuklenince var olan katalog kalemine
 * baglanma) ve Icmal'in farkli sunumlari. Kullanici "3'u sectim" diyerek
 * secer; gercek ekran sonra buna gore kurulur.
 *
 * Filament tablosu kategori > grup > kalem ic ice katlanir gruplari, grup ara
 * toplamlarini, satir icinde departman onay isaretlerini, ust ozet seridini
 * ve para birimi gecisini birlikte veremedigi icin ekran React'tir
 * (resources/js/dashboards/cost-lab.js, konelsis-dash.css; D-157 / D-173
 * onayli yol). Etkilesimler denemedir; veritabani okunmaz ve yazilmaz.
 * Katalogun parcasidir; silinmez.
 */
class UiCostItems extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'maliyet-kalemleri';

    protected string $view = 'filament.dashboards.app';

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.cost_items');
    }

    public function getTitle(): string | Htmlable
    {
        return __('ui_gallery.pages.cost_items');
    }

    /** Basligi ve tasarim seciciyi React cizer. */
    public function getHeading(): string | Htmlable
    {
        return '';
    }

    /**
     * Calisma arayuzu (D-173 ile ayni kural): gelistirme ortami, ozellik acik, tam yetkili kisi.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return DepartmentDashboards::canPreview($user instanceof Personnel ? $user : null, Feature::UiCostItems);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['root' => 'cost-lab', 'config' => CostLabConfig::make()];
    }
}
