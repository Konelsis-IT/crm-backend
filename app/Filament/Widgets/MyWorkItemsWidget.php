<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Platform\Feature;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemPriority;
use App\Enums\Report\WorkItemStatus;
use App\Filament\Resources\WorkItems\WorkItemActions;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\CodeLine;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\WorkItemPresenter;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
 *   "zaten sadece bana ait isler gorulmelidir"). D-217 (15 Ekim 2026): kapsam
 *   secimi geri geldi: Benim isler / Verdigim isler / Tum sirket (yalniz tum
 *   kartlari goren); satirdaki simgeyle durum degisir (Durumu degistir).
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

    /** Kapsam (D-217): mine, assigned, all. */
    public string $scope = 'mine';

    /** D-219: secili proje ve departman (bos = hepsi). */
    public ?int $project = null;

    public ?int $unit = null;

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
        $scopes = $this->scopes($me, $queries);
        $scope = in_array($this->scope, $scopes, true) ? $this->scope : 'mine';
        $filters = $this->filters();
        $counts = $queries->agendaCounts($me, $scope, $filters);
        $active = $this->activeGroup($counts);

        return $table
            ->heading(__('work_item.dashboard.heading'))
            ->query(fn (): Builder => $queries->agendaQuery($me, $active, $scope, $filters))
            // D-217: kapsam secimi basligin saginda (tek kapsamda gorunmez);
            // D-219: yaninda kucuk proje ve departman secimi.
            ->headerActions([
                ...(count($scopes) > 1 ? array_map(fn (string $option): Action => $this->scopeTab($option, $scope), $scopes) : []),
                ...$this->filterMenus($me, $queries, $scope),
            ])
            ->toolbarActions(array_map(fn (string $group): Action => $this->tab($group, $active, $counts[$group]), WorkItemQueries::AGENDA_GROUPS))
            // D-217: ayni listede durum degistirme (simge + ipucu, D-125).
            ->recordActions(FeatureFlags::enabled(Feature::DashboardAgendaScopes) ? [
                WorkItemActions::changeStatus()
                    ->iconButton()
                    ->tooltip(__('work_item.actions.change_status'))
                    ->after(fn () => $this->resetTable()),
            ] : [])
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
            ->recordClasses(fn (WorkItem $record): ?string => trim(implode(' ', [
                (int) $record->getAttribute('agenda_group') === 0 ? 'kc-overdue' : '',
                // D-219: kritik is satiri kirmizi tonlu (dashboard ve panoda kritiklik gorunur).
                WorkItemQueries::extendedUi() ? 'kc-priority-'.$record->priorityLevel()->value : '',
            ])) ?: null)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('work_item.dashboard.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->columns([
                // Genis sutun, en fazla iki satir; tamami ustune gelince.
                // Is kodu (B56, D-204) basligin altinda kucuk yazi (CodeLine).
                CodeLine::under(
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
                    fn (WorkItem $record): ?string => $record->getAttribute('item_no'),
                    // D-216: ust yonetimin talep ettigi acik is (grubunun ustunde).
                    fn (WorkItem $record): ?string => app(WorkItemPresenter::class)->management($record) ? __('work_item.request_link.management') : null,
                ),
                TextColumn::make('project.display_name')
                    ->label(__('work_item.dashboard.columns.project'))
                    ->placeholder('–'),
                // D-219: dort seviyeli kritiklik.
                TextColumn::make('priority')
                    ->label(__('work_item.fields.priority'))
                    ->state(fn (WorkItem $record): WorkItemPriority => $record->priorityLevel())
                    ->badge()
                    ->visible(fn (): bool => WorkItemQueries::extendedUi()),
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
                    // D-219: diger sorumlular ya da Tum ekip alt satirda.
                    ->description(fn (WorkItem $record): ?string => $this->sharedLine($record))
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

    /**
     * Kisinin secebildigi kapsamlar (D-217). Ozellik kapaliysa yalniz kendi isleri.
     *
     * @return list<string>
     */
    private function scopes(Personnel $me, WorkItemQueries $queries): array
    {
        if (! FeatureFlags::enabled(Feature::DashboardAgendaScopes)) {
            return ['mine'];
        }

        return array_values(array_filter(WorkItemQueries::AGENDA_SCOPES, fn (string $scope): bool => $queries->agendaScopeAllowed($me, $scope)));
    }

    private function scopeTab(string $scope, string $active): Action
    {
        return Action::make('scope_'.$scope)
            ->label(__('work_item.dashboard.scopes.'.$scope))
            ->link()
            ->color($scope === $active ? 'primary' : 'gray')
            ->extraAttributes(['class' => 'kc-tab'.($scope === $active ? ' kc-tab-active' : '')])
            ->action(function () use ($scope): void {
                $this->scope = $scope;
                $this->from = null;
                $this->resetTable();
            });
    }

    /**
     * Secili proje ve departman (D-219); ozellik kapaliysa secim yok.
     *
     * @return array{project?: int, unit?: int}
     */
    private function filters(): array
    {
        if (! FeatureFlags::enabled(Feature::DashboardAgendaFilters)) {
            return [];
        }

        return array_filter(['project' => (int) $this->project, 'unit' => (int) $this->unit], fn (int $id): bool => $id > 0);
    }

    /**
     * Kucuk acilir secimler (D-219; kullanici 16 Ekim 2026: "Görevlerim ve
     * işlerim kısmında proje ve departman seçim küçük bir dropdown alanı
     * olsun"). Secenekler kapsamdaki islerin projeleri ve departmanlaridir.
     *
     * @return list<ActionGroup>
     */
    private function filterMenus(Personnel $me, WorkItemQueries $queries, string $scope): array
    {
        if (! FeatureFlags::enabled(Feature::DashboardAgendaFilters)) {
            return [];
        }

        $facets = $queries->agendaFacets($me, $scope);
        $projects = $facets['projects'];
        $units = $facets['units'];

        if ($this->project !== null && ! isset($projects[$this->project])) {
            $projects = [$this->project => (string) ($queries->projectNames()[$this->project] ?? '–'), ...$projects];
        }

        if ($this->unit !== null && ! isset($units[$this->unit])) {
            $units = [$this->unit => (string) ($queries->units()[$this->unit]['name'] ?? '–'), ...$units];
        }

        return [
            $this->filterMenu('project', $projects, $this->project, Heroicon::OutlinedBriefcase),
            $this->filterMenu('unit', $units, $this->unit, Heroicon::OutlinedBuildingOffice2),
        ];
    }

    /** @param  array<int, string>  $options */
    private function filterMenu(string $name, array $options, ?int $selected, Heroicon $icon): ActionGroup
    {
        $all = __('work_item.dashboard.filters.all_'.($name === 'project' ? 'projects' : 'units'));
        $actions = [
            Action::make($name.'_all')
                ->label($all)
                ->icon($selected === null ? Heroicon::Check : null)
                ->action(fn () => $this->pick($name, null)),
        ];

        foreach ($options as $id => $label) {
            $actions[] = Action::make($name.'_'.$id)
                ->label($label)
                ->icon($selected === (int) $id ? Heroicon::Check : null)
                ->action(fn () => $this->pick($name, (int) $id));
        }

        return ActionGroup::make($actions)
            ->label($selected !== null ? ($options[$selected] ?? $all) : __('work_item.dashboard.filters.'.$name))
            ->icon($icon)
            ->button()
            ->size('sm')
            ->color($selected !== null ? 'primary' : 'gray')
            ->dropdownMaxHeight('20rem')
            ->dropdownPlacement('bottom-end')
            ->extraAttributes(['class' => 'kc-agenda-filter']);
    }

    private function pick(string $name, ?int $id): void
    {
        $this->{$name} = $id;
        $this->from = null;
        $this->resetTable();
    }

    /** Sorumlu sutununun alt satiri (D-219). */
    private function sharedLine(WorkItem $record): ?string
    {
        if (! WorkItemQueries::extendedUi()) {
            return null;
        }

        if ((bool) $record->getAttribute('for_everyone')) {
            return __('work_item.values.for_everyone');
        }

        $count = $record->relationLoaded('assignees') ? $record->assignees->count() : 0;

        return $count > 0 ? '+'.$count.' · '.__('work_item.dashboard.shared') : null;
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
            WorkItemLinkKind::PurchaseNeed => [Heroicon::OutlinedShoppingCart, 'info'],
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
