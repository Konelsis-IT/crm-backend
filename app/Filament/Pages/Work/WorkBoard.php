<?php

declare(strict_types=1);

namespace App\Filament\Pages\Work;

use App\Filament\NavigationGroup;
use App\Filament\Support\WorkAppConfig;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Is panosu (B36, D-115; 22 Eylul 2026 kullanici karari: pano, suzgecler,
 * pencereler, surukle-birak ve is tarihi React ile, taslak arayuzle birebir).
 * Tek sayfa, dort tip: Panom, Ekip panosu, Proje panosu, Yonetim panosu.
 * Basligi, dugmeleri ve kirinti yolunu React cizer; Filament sayfa basligi
 * bos birakilir. Betikler bu sayfaya ozel BODY_END kancasindan gelir
 * (filament.work.scripts).
 */
class WorkBoard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'raporlar/pano';

    // Filament 5'te $view statik DEGILDIR (BasePage::$view).
    protected string $view = 'filament.work.app';

    public static function getNavigationLabel(): string
    {
        return __('work_item.nav.board');
    }

    public function getTitle(): string | Htmlable
    {
        return __('work_item.board.title');
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
        $user = auth()->user();

        return SchemaReadiness::hasBatch('B36')
            && $user instanceof Personnel
            && Gate::forUser($user)->allows('viewAny', WorkItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['root' => 'work-board', 'config' => WorkAppConfig::board()];
    }
}
