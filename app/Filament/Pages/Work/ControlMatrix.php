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
 * Kontrol matrisi (B36, D-115; gunluk doldurma D-116; React): gun ya da
 * hafta secilir, bolum sekmesi, personel x kriter. Gunluk gorunumde Kaydet
 * kisi basina bir gunluk kontrol raporu yazar; haftalik gorunum o gunlerin
 * toplamidir ve salt okunurdur.
 *
 * Ekrani IK (`ControlMatrix:WorkItem`) ve ust yonetim acar; yalniz IK doldurur.
 */
class ControlMatrix extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    // Sol menude yok; ust cubuktaki dugmeyle acilir (24 Eylul 2026).
    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'raporlar/kontrol-matrisi';

    protected string $view = 'filament.work.app';

    public static function getNavigationLabel(): string
    {
        return __('work_item.nav.matrix');
    }

    public function getTitle(): string | Htmlable
    {
        return __('work_item.matrix.title');
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
            && Gate::forUser($user)->allows('controlMatrix', WorkItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['root' => 'work-matrix', 'config' => WorkAppConfig::matrix()];
    }
}
