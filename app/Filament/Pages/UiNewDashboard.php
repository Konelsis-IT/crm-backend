<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Clusters\UiGallery;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Support\ActionColors;
use App\Filament\Widgets\UiLab\NewDashboardList;
use App\Filament\Widgets\UiLab\NewDashboardStats;
use App\Models\Personnel\Personnel;
use App\Query\Ui\NewDashboardQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * UI Deneme > Yeni pano (Filament taslagi) (D-207): ilk deneme. Kullanici
 * React istedi ("Filament ile yapalim demedim ben react ile yap"); asil taslak
 * UiNewDashboardApp. Kalici katalog kurali geregi bu varyant silinmez.
 *
 * UI Deneme > Yeni pano taslagi (D-207). 12 Ekim 2026 toplanti notlarindaki
 * pano istegi; eski pano parcalari kullanilmaz (kullanici 14 Ekim 2026: "eski
 * dashboard parcalarini bosver bu yeni tasarim olacak"). Gercek veriden okur,
 * hicbir kayit degistirmez.
 *
 * - Alim paneli: ustte standart veri girisi, "detaya bogulmadan" dun / bugun /
 *   yarin, ortada ustte sabit sekmeler (Verilecek, Ihaleler, Verilen - sonuc
 *   bekleyen, Standart formlar, Sonuclanan en arkada), altta istatistik.
 *   Verilecekler dereceye (1-2-3), verilenler en uzun bekleyene gore sirali.
 * - Yonetici - bekleyenler: is gelistirme, teklif, proje-operasyon (onaylar),
 *   satin alma sutunlari.
 * - Satin alma paneli: veri girisi, devam eden proje, bekleyen siparis,
 *   uretim sureci; altta istatistik.
 *
 * Kalici katalogun parcasidir, silinmez.
 */
class UiNewDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'yeni-pano-filament';

    public static function getNavigationLabel(): string
    {
        return __('new_dashboard.nav_filament');
    }

    public function getTitle(): string
    {
        return __('new_dashboard.title_filament');
    }

    public function getSubheading(): ?string
    {
        return __('new_dashboard.subheading');
    }

    /** Calisma arayuzu (D-91): yalniz gelistirme ortaminda, tam yetkili rol. */
    public static function canAccess(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        $user = auth()->user();

        return FeatureFlags::enabled(Feature::UiGallery) && $user instanceof Personnel && app(RoleResolver::class)->hasFullAccess($user);
    }

    public function content(Schema $schema): Schema
    {
        $queries = app(NewDashboardQueries::class);
        $stats = $queries->acquisitionStats();

        return $schema->components([
            Tabs::make('panels')
                ->persistTabInQueryString('pano')
                ->tabs([
                    Tab::make(__('new_dashboard.tabs.acquisition'))
                        ->id('alim')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->schema([
                            $this->entrySection([
                                [TenderNoticeResource::class, 'tender', Heroicon::OutlinedMegaphone],
                                [BusinessCaseResource::class, 'case', Heroicon::OutlinedBriefcase],
                                [ProposalResource::class, 'proposal', Heroicon::OutlinedClipboardDocumentList],
                                [MeetingPlanResource::class, 'meeting', Heroicon::OutlinedCalendarDays],
                                [DocumentResource::class, 'document', Heroicon::OutlinedDocumentArrowUp],
                            ]),
                            // "Detaya bogulmadan temel ihtiyaclar: dun, bugun, yarin" - sade liste.
                            Grid::make(['default' => 1, 'lg' => 3])->schema([
                                $this->daySection('yesterday', Heroicon::OutlinedArrowUturnLeft),
                                $this->daySection('today', Heroicon::OutlinedSun),
                                $this->daySection('tomorrow', Heroicon::OutlinedArrowRight),
                            ]),
                            Tabs::make('acquisition_lists')
                                ->extraAttributes(['class' => 'kc-sticky-tabs'])
                                ->tabs([
                                    $this->listTab('to_submit', Heroicon::OutlinedClipboardDocumentList, $stats['to_submit']),
                                    $this->listTab('tenders', Heroicon::OutlinedMegaphone, $queries->openTenders()->count()),
                                    $this->listTab('awaiting', Heroicon::OutlinedPaperAirplane, $stats['awaiting']),
                                    $this->listTab('standard_docs', Heroicon::OutlinedDocumentCheck, null),
                                    $this->listTab('concluded', Heroicon::OutlinedArchiveBox, $stats['won'] + $stats['lost']),
                                ]),
                            Section::make(__('new_dashboard.sections.stats'))
                                ->icon(Heroicon::OutlinedChartBar)
                                ->schema([$this->widget('acquisition', NewDashboardStats::class)]),
                        ]),
                    Tab::make(__('new_dashboard.tabs.executive'))
                        ->id('yonetici')
                        ->icon(Heroicon::OutlinedEye)
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2, 'xl' => 4])->schema([
                                $this->widget('exec_cases'),
                                $this->widget('exec_offers'),
                                $this->widget('exec_approvals'),
                                $this->widget('exec_purchasing'),
                            ]),
                        ]),
                    Tab::make(__('new_dashboard.tabs.purchasing'))
                        ->id('satin-alma')
                        ->icon(Heroicon::OutlinedShoppingCart)
                        ->schema([
                            $this->entrySection([
                                [PurchaseRequestResource::class, 'purchase_request', Heroicon::OutlinedPencilSquare],
                                [PurchaseQuotationResource::class, 'purchase_quotation', Heroicon::OutlinedDocumentText],
                            ]),
                            Grid::make(['default' => 1, 'lg' => 2, 'xl' => 4])->schema([
                                $this->widget('pur_entry'),
                                $this->widget('pur_projects'),
                                $this->widget('pur_orders'),
                                $this->widget('pur_production'),
                            ]),
                            Section::make(__('new_dashboard.sections.stats'))
                                ->icon(Heroicon::OutlinedChartBar)
                                ->schema([$this->widget('purchasing', NewDashboardStats::class)]),
                        ]),
                ]),
        ]);
    }

    /**
     * "Veri girisi - standart": yeni kayit dugmeleri (yalniz olusturabildigi kayitlar).
     *
     * @param  list<array{0: class-string<resource>, 1: string, 2: Heroicon}>  $targets
     */
    private function entrySection(array $targets): Section
    {
        $actions = [];

        foreach ($targets as [$resource, $key, $icon]) {
            $actions[] = Action::make('new_'.$key)
                ->label(__('new_dashboard.entry.'.$key))
                ->icon($icon)
                ->color(ActionColors::CREATE)
                ->visible(fn (): bool => $this->canCreate($resource))
                ->url(fn (): string => $resource::getUrl('create'));
        }

        return Section::make(__('new_dashboard.sections.entry'))
            ->description(__('new_dashboard.sections.entry_help'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->compact()
            ->schema([Actions::make($actions)]);
    }

    /** Gun kutusu: baslikta gun ve sayi, altinda simgeli bagli satirlar; bossa tek satir. */
    private function daySection(string $bucket, Heroicon $icon): Section
    {
        $rows = NewDashboardList::dayRows($bucket);

        return Section::make(__('new_dashboard.headings.day_'.$bucket).' · '.count($rows))
            ->icon($icon)
            ->iconColor($bucket === 'today' ? 'info' : 'gray')
            ->compact()
            ->schema([
                RepeatableEntry::make('day_'.$bucket)
                    ->label(__('new_dashboard.headings.day_'.$bucket))
                    ->hiddenLabel()
                    ->state($rows)
                    ->contained(false)
                    ->schema([
                        TextEntry::make('url')->hidden(),
                        TextEntry::make('icon')->hidden(),
                        TextEntry::make('line')->hidden(),
                        TextEntry::make('title')
                            ->label(__('new_dashboard.columns.day'))
                            ->hiddenLabel()
                            ->weight('medium')
                            ->icon(fn (Get $get): mixed => $get('icon'))
                            ->url(fn (Get $get): ?string => $get('url'))
                            ->helperText(fn (Get $get): ?string => $get('line')),
                    ])
                    ->placeholder(__('new_dashboard.empty.day_'.$bucket)),
            ]);
    }

    private function listTab(string $kind, Heroicon $icon, ?int $count): Tab
    {
        return Tab::make(__('new_dashboard.lists.'.$kind))
            ->id($kind)
            ->icon($icon)
            ->badge($count)
            ->schema([$this->widget($kind)]);
    }

    /** Ayni sayfada birden cok widget: her birine kendi anahtari (Livewire). */
    private function widget(string $kind, string $class = NewDashboardList::class): Livewire
    {
        return Livewire::make($class, ['kind' => $kind])->key('new-dashboard-'.$kind);
    }

    /** @param  class-string<resource>  $resource */
    private function canCreate(string $resource): bool
    {
        try {
            return $resource::canAccess() && $resource::hasPage('create') && $resource::canCreate();
        } catch (Throwable) {
            return false;
        }
    }
}
