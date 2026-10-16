<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\UiGallery;
use App\Filament\Concerns\InteractsWithNewDashboard;
use App\Support\Dashboard\DepartmentDashboards;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * UI Deneme > Yeni pano (D-207, React). Kullanici 14 Ekim 2026: "Filament ile
 * yapalim demedim ben react ile yap. Daha cok alan yonetimi daha fazla bilgi
 * ama yine ayni boyutlara sigacak sekilde olmalidir. Acil koduyla ustte duyuru
 * paneli uyarisi olur. Ekranda bir anda beliren carpi ile kapatilan acil
 * yapilacak islem bildirilir, belirli periyotlarla ekranda yeniden cikar.
 * Isin acilligi ve surenin sikismasina gore degisir."
 *
 * Gorunum yalniz React kok ogesini ve stil baglantisini icerir; betikler
 * sayfaya ozel BODY_END kancasindan gelir (filament.new-dashboard.scripts).
 * Veri NewDashboardConfig'ten. Eski pano parcalari (dash-*) kullanilmaz.
 * Kalici katalogun parcasidir, silinmez.
 *
 * D-208: yonetici islemleri (Onay ver, Hemen uyar, Ozel bildirim, Departmani
 * cagir, Uyari gonder) InteractsWithNewDashboard'dan gelir; D-212 ile ayni
 * pano departmana gore Genel bakista da acilir (Dashboard).
 */
class UiNewDashboardApp extends Page
{
    use InteractsWithNewDashboard;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'yeni-pano';

    protected string $view = 'filament.new-dashboard.app';

    public static function getNavigationLabel(): string
    {
        return __('new_dashboard.nav');
    }

    public function getTitle(): string | Htmlable
    {
        return __('new_dashboard.title');
    }

    /** Basligi, sekmeleri ve uyarilari React cizer. */
    public function getHeading(): string | Htmlable
    {
        return '';
    }

    /** Ayni erisim kurali: gelistirme ortami, UI Deneme acik, tam yetkili kisi. */
    public static function canAccess(): bool
    {
        return UiNewDashboard::canAccess();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['config' => $this->newDashboardConfig()];
    }

    /** UI Deneme butun panolari gosterir. */
    protected function newDashboardPanels(): array
    {
        return DepartmentDashboards::ALL;
    }
}
