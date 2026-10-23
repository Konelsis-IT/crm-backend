<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Clusters\UiGallery;
use App\Filament\Support\ReportLabConfig;
use App\Models\Personnel\Personnel;
use App\Support\Dashboards\DepartmentDashboards;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * UI Deneme > Rapor yazma denemesi (D-240, 23 Ekim 2026 kullanici istegi:
 * "Tum rapor tiplerinin rapor arayuzunun tasarimini react ile modern benzer
 * formatta bir arayuze cevirelim ... rapor icine is olusturma mantigi nasil
 * dizayn edilebilirse raporlar kismindaki tum rapor tipleri icin ornek
 * calismalarini UI deneme kismina ekle ... Inanilmaz basit ve temiz bir arayuz
 * lazim insanlari yormamaliyiz").
 *
 * Her rapor tipi gercek taslagin alanlariyla cizilir (ReportLabConfig); metin
 * alanlari Akilli is ekle duzenleyicisiyle (rozetli duz yazi), rapordan cikan
 * isler ayni yazma alaniyla eklenir. Deneme: kayit yoktur, veritabani yalniz
 * okunur. Ekran React'tir (resources/js/work/report-lab.js). Katalogun
 * parcasidir; silinmez.
 */
class UiReportLab extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'rapor-yazma';

    protected string $view = 'filament.work.app';

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.report_lab');
    }

    public function getTitle(): string | Htmlable
    {
        return __('ui_gallery.pages.report_lab');
    }

    /** Basligi React cizer. */
    public function getHeading(): string | Htmlable
    {
        return '';
    }

    /** Calisma arayuzu (D-173 ile ayni kural): gelistirme ortami, ozellik acik, tam yetkili kisi. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return DepartmentDashboards::canPreview($user instanceof Personnel ? $user : null, Feature::UiReportLab);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['root' => 'report-lab', 'config' => ReportLabConfig::make()];
    }
}
