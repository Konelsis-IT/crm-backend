<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Platform\Feature;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemStatus;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Genel bakis: "Görevlerim ve işlerim" (D-146, 30 Eylul 2026 kullanici
 * tasarimi). Bugunun ve yarinin kartlari ile onceki gunlerden devreden acik
 * kartlar; Geciken / Bugün / Yarın gruplari.
 *
 * - Yalniz kisinin kendi kartlari (D-147, 30 Eylul 2026 kullanici karari:
 *   "zaten sadece bana ait isler gorulmelidir"); kapsam secimi yoktur.
 * - Sekme, listenin hangi gruptan baslayacagini secer: Geciken hepsini,
 *   Bugün bugun ve yarini, Yarın yalniz yarini gosterir; secili grubun
 *   basligi sekmede yazdigi icin tabloda tekrarlanmaz (konelsis.css).
 * - Satira tiklayinca kart acilir (D-125). Sorumlu sutununda kisi simgesi
 *   yerine avatar vardir (tasarim geregi, D-146).
 */
class MyWorkItemsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    /** Secili sekme; bos ise ilk dolu grup. */
    public ?string $from = null;

    public static function canView(): bool
    {
        return FeatureFlags::enabled(Feature::DashboardAgenda)
            && FeatureFlags::enabled(Feature::WorkItems)
            && SchemaReadiness::hasBatch('B36')
            && auth()->user() instanceof Personnel;
    }

    public function table(Table $table): Table
    {
        $me = auth()->user();
        abort_unless($me instanceof Personnel, 403);

        $queries = app(WorkItemQueries::class);
        $counts = $queries->agendaCounts($me);
        $active = $this->activeGroup($counts);

        return $table
            ->heading(__('work_item.dashboard.heading'))
            ->query(fn (): Builder => $queries->agendaQuery($me, $active))
            ->toolbarActions(array_map(fn (string $group): Action => $this->tab($group, $active, $counts[$group]), WorkItemQueries::AGENDA_GROUPS))
            ->groups([
                Group::make('agenda_group')
                    ->getKeyFromRecordUsing(fn (WorkItem $record): string => (string) $record->getAttribute('agenda_group'))
                    ->getTitleFromRecordUsing(function (WorkItem $record) use ($counts): string {
                        $group = WorkItemQueries::AGENDA_GROUPS[(int) $record->getAttribute('agenda_group')] ?? 'today';

                        return __('work_item.dashboard.tabs.'.$group, ['count' => $counts[$group]]);
                    })
                    // Siralama sorgudadir (grup ifadesi once).
                    ->orderQueryUsing(fn (Builder $query): Builder => $query)
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('agenda_group')
            ->groupingSettingsHidden()
            ->recordUrl(fn (WorkItem $record): string => WorkItemResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (WorkItem $record): ?string => (int) $record->getAttribute('agenda_group') === 0 ? 'kc-overdue' : null)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('work_item.dashboard.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->columns([
                // Genis sutun, en fazla iki satir; tamami ustune gelince.
                TextColumn::make('title')
                    ->label(__('work_item.dashboard.columns.title'))
                    ->weight(FontWeight::Medium)
                    ->icon(fn (WorkItem $record): Heroicon => $this->kind($record)[0])
                    ->iconColor(fn (WorkItem $record): string => $this->kind($record)[1])
                    ->extraAttributes(['class' => 'kc-tile-icon'])
                    ->width('38%')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (WorkItem $record): ?string => mb_strlen((string) $record->title) > 70 ? (string) $record->title : null),
                TextColumn::make('project.name')
                    ->label(__('work_item.dashboard.columns.project'))
                    ->placeholder('–'),
                // Tasarimdaki gibi rozet basinda nokta; durum simgesi yok.
                TextColumn::make('status')
                    ->label(__('work_item.dashboard.columns.status'))
                    ->badge()
                    ->icon(false)
                    ->extraAttributes(['class' => 'kc-dot-badge']),
                TextColumn::make('work_on')
                    ->label(__('work_item.dashboard.columns.date'))
                    ->formatStateUsing(fn (WorkItem $record): string => $this->day($record)->translatedFormat('j F Y'))
                    ->description(fn (WorkItem $record): string => $this->day($record)->translatedFormat('l'))
                    ->color(fn (WorkItem $record): ?string => (int) $record->getAttribute('agenda_group') === 0 ? 'danger' : null),
                ImageColumn::make('avatar')
                    ->label(__('work_item.dashboard.columns.owner'))
                    ->state(fn (WorkItem $record): ?string => $record->personnel !== null ? filament()->getUserAvatarUrl($record->personnel) : null)
                    ->circular()
                    ->imageSize(28)
                    ->width('1%')
                    // Avatar ismin hemen solunda (sutun "Sorumlu" basligi kadar genis).
                    ->alignEnd(),
                TextColumn::make('personnel.full_name')
                    ->label('')
                    ->icon(null),
                // Satir sonundaki ok; ekran okuyucu "Aç" okur.
                IconColumn::make('open')
                    ->label('')
                    ->state(__('app.actions.open'))
                    ->icon(Heroicon::ChevronRight)
                    ->size(IconSize::Small)
                    ->color('gray')
                    ->alignEnd(),
            ]);
    }

    /** Secili sekme: kullanicinin sectigi, yoksa ilk dolu grup. */
    private function activeGroup(array $counts): string
    {
        if ($this->from !== null && in_array($this->from, WorkItemQueries::AGENDA_GROUPS, true) && $counts[$this->from] > 0) {
            return $this->from;
        }

        foreach (WorkItemQueries::AGENDA_GROUPS as $group) {
            if ($counts[$group] > 0) {
                return $group;
            }
        }

        return 'overdue';
    }

    private function tab(string $group, string $active, int $count): Action
    {
        return Action::make('tab_'.$group)
            ->label(__('work_item.dashboard.tabs.'.$group, ['count' => $count]))
            ->link()
            ->color($group === $active ? 'primary' : 'gray')
            ->disabled($count === 0)
            ->extraAttributes(['class' => 'kc-tab'.($group === $active ? ' kc-tab-active' : '')])
            ->action(function () use ($group): void {
                $this->from = $group;
                // Tablo istek basinda kuruldu; yeni sekmeyle yeniden kurulur.
                $this->resetTable();
            });
    }

    private function day(WorkItem $record): CarbonImmutable
    {
        return CarbonImmutable::parse($record->work_on?->format('Y-m-d') ?? 'today', DisplayTime::zone())->locale(app()->getLocale());
    }

    /**
     * Baslik simgesi ve kutucuk rengi: bagli kayit turune, yoksa kategoriye gore.
     *
     * @return array{0: Heroicon, 1: string}
     */
    private function kind(WorkItem $record): array
    {
        if ($record->status === WorkItemStatus::Done) {
            return [Heroicon::OutlinedCheck, 'success'];
        }

        return match ($record->link_kind) {
            WorkItemLinkKind::Document => [Heroicon::OutlinedDocumentText, 'warning'],
            WorkItemLinkKind::Proposal => [Heroicon::OutlinedClipboardDocumentList, 'warning'],
            WorkItemLinkKind::BusinessCase => [Heroicon::OutlinedFolder, 'warning'],
            WorkItemLinkKind::MeetingPlan => [Heroicon::OutlinedUserGroup, 'warning'],
            WorkItemLinkKind::WorkRequest => [Heroicon::OutlinedInboxArrowDown, 'info'],
            WorkItemLinkKind::TenderNotice => [Heroicon::OutlinedMegaphone, 'info'],
            WorkItemLinkKind::SupplyItem => [Heroicon::OutlinedTruck, 'info'],
            default => match ($record->category_code) {
                'meeting' => [Heroicon::OutlinedUserGroup, 'success'],
                'correspondence' => [Heroicon::OutlinedEnvelope, 'gray'],
                'site_work', 'field_support', 'commissioning' => [Heroicon::OutlinedWrenchScrewdriver, 'gray'],
                'new_proposal', 'revised_proposal', 'costing' => [Heroicon::OutlinedClipboardDocumentList, 'warning'],
                default => [Heroicon::OutlinedDocument, 'gray'],
            },
        };
    }
}
