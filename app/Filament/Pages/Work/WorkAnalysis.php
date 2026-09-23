<?php

declare(strict_types=1);

namespace App\Filament\Pages\Work;

use App\Filament\Clusters\WorkReports;
use App\Filament\Support\WorkAppConfig;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Is raporlari > Analiz panosu (B36, D-115; React): alti gosterge,
 * departmana gore saat, zaman dagilimi, haftalik tamamlanan is, en cok
 * bekleten taraflar, en uzun suredir acik isler. Her kutu ilgili rapora acilir.
 */
class WorkAnalysis extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $cluster = WorkReports::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'analiz';

    protected string $view = 'filament.work.app';

    public static function getNavigationLabel(): string
    {
        return __('work_item.nav.analysis');
    }

    public function getTitle(): string | Htmlable
    {
        return __('work_item.analysis.title');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        return WorkReports::canAccess();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['root' => 'work-analysis', 'config' => WorkAppConfig::analysis()];
    }
}
