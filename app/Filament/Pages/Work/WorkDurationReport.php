<?php

declare(strict_types=1);

namespace App\Filament\Pages\Work;

use App\Filament\Clusters\WorkReports;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkAnalysisQueries;
use App\Reports\Work\WorkCategoryCatalog;
use App\Support\DisplayTime;
use App\Support\WorkDurationFormat;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Is raporlari > Sure raporu (B36, D-115; Filament tablosu, 22 Eylul 2026
 * kullanici karari: ana is / alt is satirlari tabloda acilip kapanir).
 *
 * Varsayilan gruplama "Ana is": grup basligi ana istir ve acilip kapanir; alt
 * kartlar altinda, grubun ozet satiri alt kartlari toplar (acilis, kapanis,
 * toplam, calisma, bekleme, engel, saat, termin sapmasi). Sureler durum
 * gecmisinden hesaplanir, elle girilmez.
 */
class WorkDurationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $cluster = WorkReports::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'sure';

    /** @var array<string, array<string, mixed>> Istek boyu ozet onbellegi (grup kimlikleri => ozet). */
    private array $summaryCache = [];

    public static function getNavigationLabel(): string
    {
        return __('work_item.nav.duration');
    }

    public function getTitle(): string | Htmlable
    {
        return __('work_item.duration.title');
    }

    public function getSubheading(): ?string
    {
        return __('work_item.duration.subheading');
    }

    public static function canAccess(): bool
    {
        return WorkReports::canAccess();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema(fn (): array => $this->kpis())
                ->columns(['default' => 2, 'md' => 3, 'xl' => 5])
                ->contained(false)
                ->gridContainer(),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $analysis = app(WorkAnalysisQueries::class);

        return $table
            ->query(fn (): Builder => $analysis->durationQuery($this->viewer()))
            ->columns([
                TextColumn::make('title')
                    ->label(__('work_item.duration.columns.item'))
                    ->wrap()
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (WorkItem $record): ?string => (int) ($record->getAttribute('children_count') ?? 0) > 0
                        ? __('work_item.duration.root_badge', ['count' => (int) $record->getAttribute('children_count')])
                        : null)
                    ->url(fn (WorkItem $record): string => WorkItemResource::getUrl('view', ['record' => $record]))
                    ->summarize($this->summary(fn (array $s): ?string => __('work_item.duration.summary_cards', ['count' => $s['count']]))),
                TextColumn::make('project.name')
                    ->label(__('work_item.duration.columns.project'))
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('personnel.full_name')
                    ->label(__('work_item.duration.columns.personnel'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category_code')
                    ->label(__('work_item.duration.columns.category'))
                    ->formatStateUsing(fn (?string $state): ?string => app(WorkCategoryCatalog::class)->label($state))
                    ->placeholder('–')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('work_at')
                    ->label(__('work_item.duration.columns.opened'))
                    ->date('d.m')
                    ->sortable()
                    ->summarize($this->summary(fn (array $s): ?string => $s['opened']?->copy()->timezone(DisplayTime::zone())->format('d.m'))),
                TextColumn::make('completed_at')
                    ->label(__('work_item.duration.columns.closed'))
                    ->date('d.m')
                    ->placeholder(__('work_item.values.open'))
                    ->summarize($this->summary(fn (array $s): ?string => $s['open'] ? __('work_item.values.open') : $s['closed']?->copy()->timezone(DisplayTime::zone())->format('d.m'))),
                TextColumn::make('total_days')
                    ->label(__('work_item.duration.columns.total'))
                    ->state(fn (WorkItem $record): ?string => WorkDurationFormat::days($record->totalDays()))
                    ->summarize($this->summary(fn (array $s): ?string => WorkDurationFormat::days($s['total_days']))),
                TextColumn::make('progress_span')
                    ->label(__('work_item.duration.columns.progress'))
                    ->state(fn (WorkItem $record): ?string => WorkDurationFormat::span($record->secondsIn('progress')))
                    ->placeholder('–')
                    ->summarize($this->summary(fn (array $s): ?string => WorkDurationFormat::span($s['progress']) ?? '–')),
                TextColumn::make('waiting_span')
                    ->label(__('work_item.duration.columns.waiting'))
                    ->state(fn (WorkItem $record): ?string => WorkDurationFormat::span($record->secondsIn('waiting')))
                    ->placeholder('–')
                    ->summarize($this->summary(fn (array $s): ?string => WorkDurationFormat::span($s['waiting']) ?? '–')),
                TextColumn::make('blocked_span')
                    ->label(__('work_item.duration.columns.blocked'))
                    ->state(fn (WorkItem $record): ?string => WorkDurationFormat::span($record->secondsIn('blocked')))
                    ->placeholder('–')
                    ->summarize($this->summary(fn (array $s): ?string => WorkDurationFormat::span($s['blocked']) ?? '–')),
                TextColumn::make('work_hours')
                    ->label(__('work_item.duration.columns.hours'))
                    ->formatStateUsing(fn ($state): ?string => WorkDurationFormat::hours($state !== null ? (float) $state : null))
                    ->placeholder('–')
                    ->summarize($this->summary(fn (array $s): ?string => WorkDurationFormat::hours($s['hours']) ?? '–')),
                TextColumn::make('due_deviation')
                    ->label(__('work_item.duration.columns.deviation'))
                    ->state(fn (WorkItem $record): ?string => WorkDurationFormat::deviation($record->dueDeviation(), $record->completed_at !== null, $record->due_on)[0])
                    ->color(fn (WorkItem $record): string => WorkDurationFormat::deviation($record->dueDeviation(), $record->completed_at !== null, $record->due_on)[1])
                    ->weight('semibold')
                    ->placeholder('–')
                    ->summarize($this->summary(fn (array $s): ?string => WorkDurationFormat::deviation($s['deviation'], ! $s['open'], $s['due'])[0] ?? '–')),
            ])
            ->groups([
                Group::make('root_key')
                    ->label(__('work_item.duration.groups.root'))
                    ->getTitleFromRecordUsing(fn (WorkItem $record): string => $this->rootTitle($record))
                    ->getDescriptionFromRecordUsing(fn (WorkItem $record): ?string => $this->rootDescription($record))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $analysis->orderByRoot($query, $direction))
                    ->scopeQueryUsing(fn (Builder $query, WorkItem $record): Builder => $analysis->scopeToRoot($query, $record->getAttribute('root_key')))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $analysis->scopeToRoot($query, $key))
                    ->collapsible(),
                Group::make('project.name')
                    ->label(__('work_item.duration.groups.project'))
                    ->collapsible(),
                Group::make('orgUnit.name')
                    ->label(__('work_item.duration.groups.unit'))
                    ->collapsible(),
                Group::make('personnel.full_name')
                    ->label(__('work_item.duration.groups.personnel'))
                    ->collapsible(),
            ])
            ->defaultGroup('root_key')
            ->filters([
                Filter::make('opened')
                    ->label(__('work_item.duration.filters.opened'))
                    ->schema([
                        DatePicker::make('from')->label(__('work_item.duration.filters.from')),
                        DatePicker::make('until')->label(__('work_item.duration.filters.until')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $analysis->applyOpenedBetween($query, $data['from'] ?? null, $data['until'] ?? null))
                    ->indicateUsing(fn (array $data): ?string => filled($data['from'] ?? null) || filled($data['until'] ?? null)
                        ? __('work_item.duration.filters.opened').': '.(filled($data['from'] ?? null) ? Carbon::parse($data['from'])->format('d.m.Y') : '…').' – '.(filled($data['until'] ?? null) ? Carbon::parse($data['until'])->format('d.m.Y') : '…')
                        : null),
                SelectFilter::make('org_unit_id')
                    ->label(__('work_item.duration.filters.unit'))
                    ->relationship('orgUnit', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('personnel_id')
                    ->label(__('work_item.duration.filters.personnel'))
                    ->relationship('personnel', 'full_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('project_id')
                    ->label(__('work_item.duration.filters.project'))
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_code')
                    ->label(__('work_item.duration.filters.category'))
                    ->options(fn (): array => app(WorkCategoryCatalog::class)->allOptions())
                    ->searchable(),
                SelectFilter::make('root')
                    ->label(__('work_item.duration.filters.root'))
                    ->options(fn (): array => $analysis->rootOptions($this->viewer()))
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $analysis->applyRoot($query, filled($data['value'] ?? null) ? (int) $data['value'] : null)),
                Filter::make('closed_only')
                    ->label(__('work_item.duration.filters.closed_only'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $analysis->applyClosedOnly($query)),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('work_item.duration.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }

    /**
     * Sayfa basi gostergeleri: suzulmus kartlarin ortalamalari.
     *
     * @return list<Stat>
     */
    private function kpis(): array
    {
        $kpis = app(WorkAnalysisQueries::class)->durationKpis($this->getFilteredTableQuery());

        return [
            Stat::make(__('work_item.duration.kpis.avg_total'), WorkDurationFormat::days($kpis['avg_total']) ?? '–'),
            Stat::make(__('work_item.duration.kpis.avg_progress'), WorkDurationFormat::days($kpis['avg_progress']) ?? '–'),
            Stat::make(__('work_item.duration.kpis.avg_waiting'), WorkDurationFormat::days($kpis['avg_waiting']) ?? '–'),
            Stat::make(__('work_item.duration.kpis.due_compliance'), $kpis['due_compliance'] !== null ? '%'.$kpis['due_compliance'] : '–'),
            Stat::make(__('work_item.duration.kpis.avg_root'), WorkDurationFormat::days($kpis['avg_root']) ?? '–')
                ->description(__('work_item.duration.kpis.footer', [
                    'roots' => $kpis['roots'],
                    'cards' => $kpis['cards'],
                    'days' => $kpis['total_days'],
                    'hours' => WorkDurationFormat::hours($kpis['hours']) ?? '0',
                ])),
        ];
    }

    /**
     * Grup (ve tablo) ozet satiri hucresi.
     *
     * @param  callable(array<string, mixed>): ?string  $format
     */
    private function summary(callable $format): Summarizer
    {
        return Summarizer::make()
            ->using(function (QueryBuilder $query) use ($format): ?string {
                $key = md5($query->toSql().'|'.json_encode($query->getBindings()));
                $this->summaryCache[$key] ??= app(WorkAnalysisQueries::class)->summarizeQuery($query);

                return $format($this->summaryCache[$key]);
            });
    }

    private function rootTitle(WorkItem $record): string
    {
        $root = $record->getAttribute('root_key');

        if ($root === null) {
            return (string) __('work_item.duration.no_root');
        }

        if ((int) $root === (int) $record->getKey()) {
            return (string) $record->title;
        }

        return (string) ($record->parent?->title ?? __('work_item.duration.no_root'));
    }

    private function rootDescription(WorkItem $record): ?string
    {
        $root = $record->getAttribute('root_key');

        if ($root === null) {
            return (string) __('work_item.duration.no_root_help');
        }

        return (string) __('work_item.duration.root_help');
    }

    private function viewer(): ?Personnel
    {
        $user = auth()->user();

        return $user instanceof Personnel ? $user : null;
    }
}
