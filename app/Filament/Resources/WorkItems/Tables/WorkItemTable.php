<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Tables;

use App\Enums\Report\WorkDiscipline;
use App\Enums\Report\WorkItemPriority;
use App\Enums\Report\WorkItemSource;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Exceptions\AbstractException;
use App\Filament\Resources\WorkItems\WorkItemActions;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\CodeLine;
use App\Filament\Support\DomainNotifications;
use App\Models\Project\Project;
use App\Models\Report\WorkItem;
use App\Filament\Support\PersonnelSelect;
use App\Filament\Support\WorkAppConfig;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\Report\WorkItemPresenter;
use App\Services\Report\WorkItemSentence;
use App\Services\Report\WorkItemService;
use App\Support\Projects\ProjectNames;
use App\Support\WorkDurationFormat;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Isler tablosu (B36, D-115): Raporlar > Isler ve personel kartindaki Isler
 * sekmesi ayni tabloyu kullanir. Sekmede personel ve departman sutunlari /
 * suzgecleri gizlenir, toplu islem yoktur.
 */
final class WorkItemTable
{
    public static function make(Table $table, bool $forPersonnel = false): Table
    {
        // D-235: Akilli is ekle acikken tek sutun (rozetli okunus), satir ve
        // Duzenle Akilli is ekle penceresini acar; is detay sayfasina gidilmez.
        $smart = WorkAppConfig::smartOn();

        return $table
            ->columns($smart ? self::sentenceColumns() : self::columns($forPersonnel))
            // D-240: arsivdeki is listede yok (Isler sayfasinda Arsiv suzgeciyle gorunur).
            ->modifyQueryUsing(fn (Builder $query): Builder => $smart ? $query->notArchived()->with(app(WorkItemQueries::class)->relations()) : $query->notArchived())
            // D-216: ust yonetimin talep ettigi acik isler once, sonra en yeni.
            ->defaultSort(fn (Builder $query): Builder => app(WorkItemQueries::class)->listOrder($query))
            ->searchable()
            ->filters(self::filters($forPersonnel), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns($forPersonnel ? 3 : 4)
            ->groups(self::groups($forPersonnel))
            ->recordActions(match (true) {
                $smart => [WorkItemActions::smartEdit()],
                $forPersonnel => [],
                default => [
                    ViewAction::make()->iconButton(),
                    EditAction::make()->iconButton(),
                ],
            })
            ->recordUrl($smart ? null : fn (WorkItem $record): string => WorkItemResource::getUrl('view', ['record' => $record]))
            ->toolbarActions($forPersonnel ? [] : [self::bulkActions()])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedQueueList)
            ->emptyStateHeading(__('work_item.table.empty'))
            ->emptyStateDescription(__('work_item.table.empty_help'));
    }

    /**
     * Tek sutun (D-235; kullanici 22 Ekim 2026: "Is olusturulurken yazilan text ve
     * badge yapisiyla yine ayni sekilde isler sekmesinde tablo yerine tek sutunda
     * tum isler ayni sekilde listeli sekilde yazilsin"): baslik, not ve Akilli is
     * ekle rozetleri (WorkItemSentence). Satira tiklamak isi pencerede acar.
     *
     * @return list<TextColumn>
     */
    private static function sentenceColumns(): array
    {
        return [
            TextColumn::make('sentence')
                ->label(__('work_item.table.item'))
                ->state(fn (WorkItem $record): Htmlable => app(WorkItemSentence::class)->html($record))
                ->html()
                ->wrap()
                ->searchable(query: fn (Builder $query, string $search): Builder => app(WorkItemQueries::class)->applySearch($query, $search))
                ->extraCellAttributes(fn (WorkItem $record): array => [
                    'x-on:click' => WorkItemActions::smartEditJs($record, 'view'),
                    'class' => 'cursor-pointer',
                ]),
        ];
    }

    /**
     * @return list<TextColumn>
     */
    private static function columns(bool $forPersonnel): array
    {
        $columns = [
            TextColumn::make('work_at')
                ->label(__('work_item.table.date'))
                ->dateTime('d.m.Y H:i')
                ->sortable(),
            // Is kodu (B56, D-204) is sutununun altinda kucuk yazi; ana is bilgisi yaninda (CodeLine).
            CodeLine::under(
                TextColumn::make('title')
                    ->label(__('work_item.table.item'))
                    ->wrap()
                    ->weight('medium')
                    ->searchable(query: fn (Builder $query, string $search): Builder => app(WorkItemQueries::class)->applySearch($query, $search)),
                fn (WorkItem $record): ?string => $record->getAttribute('item_no'),
                fn (WorkItem $record): ?string => implode(' | ', array_filter([
                    // D-216: ust yonetimin talep ettigi acik is.
                    app(WorkItemPresenter::class)->management($record) ? __('work_item.request_link.management') : null,
                    $record->parent !== null ? __('work_item.table.parent_of', ['title' => $record->parent->title]) : null,
                ])) ?: null,
            ),
            TextColumn::make('project.display_name')
                ->label(__('work_item.table.project'))
                ->placeholder('–')
                ->toggleable(),
        ];

        if (! $forPersonnel) {
            $columns[] = TextColumn::make('orgUnit.name')
                ->label(__('work_item.table.unit'))
                ->placeholder('–')
                ->toggleable();
        }

        $columns[] = TextColumn::make('category_code')
            ->label(__('work_item.table.category'))
            ->formatStateUsing(fn (?string $state): ?string => app(WorkCategoryCatalog::class)->label($state))
            ->placeholder('–')
            ->toggleable();
        $columns[] = TextColumn::make('status')
            ->label(__('work_item.table.status'))
            ->badge()
            ->sortable();

        if (! $forPersonnel) {
            $columns[] = TextColumn::make('personnel.full_name')
                ->label(__('work_item.table.personnel'))
                // D-219: diger sorumlular ve Tum ekip alt satirda.
                ->description(fn (WorkItem $record): ?string => self::sharedLine($record))
                ->toggleable();
        }

        return [
            ...$columns,
            TextColumn::make('is_critical')
                ->label(__('work_item.table.critical'))
                ->formatStateUsing(fn (bool $state): ?string => $state ? __('work_item.values.critical') : null)
                ->badge()
                ->color('danger')
                ->placeholder('–')
                ->visible(fn (): bool => ! WorkItemQueries::extendedUi())
                ->toggleable(),
            // D-219: dort seviyeli kritiklik.
            TextColumn::make('priority')
                ->label(__('work_item.table.priority'))
                ->state(fn (WorkItem $record): WorkItemPriority => $record->priorityLevel())
                ->badge()
                ->sortable(query: fn (Builder $query, string $direction): Builder => app(WorkItemQueries::class)->orderByPriority($query, $direction))
                ->visible(fn (): bool => WorkItemQueries::extendedUi())
                ->toggleable(),
            TextColumn::make('discipline')
                ->label(__('work_item.table.discipline'))
                ->badge()
                ->color('gray')
                ->placeholder('–')
                ->visible(fn (): bool => WorkItemQueries::extendedUi() && WorkItemQueries::disciplineUi())
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('requester')
                ->label(__('work_item.table.requester'))
                ->state(fn (WorkItem $record): ?string => $record->requesterLabel())
                ->placeholder('–')
                ->toggleable(isToggledHiddenByDefault: true)
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B37')),
            TextColumn::make('waiting_state')
                ->label(__('work_item.table.waiting'))
                ->state(fn (WorkItem $record): ?string => $record->status === WorkItemStatus::Waiting && $record->waitingLabel() !== null
                    ? __('work_item.values.waiting_short', ['who' => $record->waitingLabel(), 'days' => (int) $record->waitingDays()])
                    : null)
                ->color(fn (WorkItem $record): string => $record->isLongWaiting() ? 'warning' : 'gray')
                ->placeholder('–')
                ->toggleable(),
            TextColumn::make('work_hours')
                ->label(__('work_item.table.hours'))
                ->formatStateUsing(fn ($state): ?string => WorkDurationFormat::hours($state !== null ? (float) $state : null))
                ->placeholder('–')
                ->sortable()
                ->toggleable(),
            TextColumn::make('source')
                ->label(__('work_item.table.source'))
                ->toggleable(),
            TextColumn::make('link_no')
                ->label(__('work_item.table.link'))
                ->state(function (WorkItem $record): ?string {
                    $link = app(WorkItemPresenter::class)->link($record);

                    return $link === null ? null : ($link['no'] ?? $link['label']);
                })
                ->tooltip(fn (WorkItem $record): ?string => app(WorkItemPresenter::class)->link($record)['label'] ?? null)
                ->url(fn (WorkItem $record): ?string => app(WorkItemPresenter::class)->link($record)['url'] ?? null)
                ->fontFamily('mono')
                ->placeholder('–')
                ->toggleable(),
        ];
    }

    /**
     * @return array<int, Filter|SelectFilter>
     */
    private static function filters(bool $forPersonnel): array
    {
        $filters = [
            Filter::make('date')
                ->label(__('work_item.filters.date'))
                ->schema([
                    Select::make('preset')
                        ->label(__('work_item.filters.date'))
                        ->options(self::rangeOptions())
                        ->placeholder(__('work_item.filters.any_date'))
                        ->live()
                        ->native(false),
                    DatePicker::make('from')
                        ->label(__('work_item.filters.from'))
                        ->visible(fn (Get $get): bool => $get('preset') === 'range'),
                    DatePicker::make('to')
                        ->label(__('work_item.filters.to'))
                        ->visible(fn (Get $get): bool => $get('preset') === 'range'),
                ])
                ->columns(3)
                ->query(fn (Builder $query, array $data): Builder => app(WorkItemQueries::class)->applyDateFilter($query, $data['preset'] ?? null, $data['from'] ?? null, $data['to'] ?? null))
                ->indicateUsing(fn (array $data): ?string => filled($data['preset'] ?? null)
                    ? __('work_item.filters.date').': '.(self::rangeOptions()[$data['preset']] ?? '')
                    : null),
        ];

        if (! $forPersonnel) {
            $filters[] = SelectFilter::make('org_unit_id')
                ->label(__('work_item.filters.unit'))
                ->relationship('orgUnit', 'name')
                ->multiple()
                ->preload();
            $filters[] = SelectFilter::make('personnel_id')
                ->label(__('work_item.filters.personnel'))
                ->relationship('personnel', 'full_name')
                ->multiple()
                ->searchable()
                ->preload();
        }

        return [
            ...$filters,
            SelectFilter::make('project_id')
                ->label(__('work_item.filters.project'))
                ->relationship('project', 'name')
                // D-174: kisa ad (yoksa lisans adi); iki adla da aranir.
                ->getOptionLabelFromRecordUsing(fn (Project $record): string => ProjectNames::optionLabel($record))
                ->multiple()
                ->searchable(ProjectNames::searchColumns())
                ->preload(),
            SelectFilter::make('category_code')
                ->label(__('work_item.filters.category'))
                ->options(fn (): array => app(WorkCategoryCatalog::class)->allOptions())
                ->multiple()
                ->searchable(),
            SelectFilter::make('status')
                ->label(__('work_item.filters.status'))
                ->options(['open' => __('work_item.filters.open'), ...WorkItemStatus::options()])
                ->multiple()
                ->default($forPersonnel ? null : ['open'])
                ->query(fn (Builder $query, array $data): Builder => app(WorkItemQueries::class)->applyStatuses($query, (array) ($data['values'] ?? []))),
            SelectFilter::make('source')
                ->label(__('work_item.filters.source'))
                ->options(WorkItemSource::class),
            Filter::make('critical')
                ->label(__('work_item.filters.critical'))
                ->toggle()
                ->visible(fn (): bool => ! WorkItemQueries::extendedUi())
                ->query(fn (Builder $query): Builder => app(WorkItemQueries::class)->applyCritical($query)),
            // D-219: kritiklik ve is grubu suzgecleri.
            SelectFilter::make('priority')
                ->label(__('work_item.filters.priority'))
                ->options(WorkItemPriority::class)
                ->multiple()
                ->visible(fn (): bool => WorkItemQueries::extendedUi())
                ->query(fn (Builder $query, array $data): Builder => app(WorkItemQueries::class)->applyPriorities($query, array_map(fn ($value): string => $value instanceof \BackedEnum ? (string) $value->value : (string) $value, (array) ($data['values'] ?? [])))),
            SelectFilter::make('discipline')
                ->label(__('work_item.filters.discipline'))
                ->options(WorkDiscipline::class)
                ->multiple()
                ->visible(fn (): bool => WorkItemQueries::extendedUi() && WorkItemQueries::disciplineUi()),
            Filter::make('long_waiting')
                ->label(__('work_item.filters.long_waiting'))
                ->toggle()
                ->query(fn (Builder $query): Builder => app(WorkItemQueries::class)->applyLongWaiting($query)),
        ];
    }

    /**
     * @return list<Group>
     */
    private static function groups(bool $forPersonnel): array
    {
        $groups = [
            Group::make('project.name')->label(__('work_item.table.project'))
                ->getTitleFromRecordUsing(fn (WorkItem $record): string => $record->project?->display_name ?? '–')
                ->collapsible(),
        ];

        if (! $forPersonnel) {
            $groups[] = Group::make('orgUnit.name')->label(__('work_item.table.unit'))->collapsible();
            $groups[] = Group::make('personnel.full_name')->label(__('work_item.table.personnel'))->collapsible();
        }

        $groups[] = Group::make('category_code')
            ->label(__('work_item.table.category'))
            ->getTitleFromRecordUsing(fn (WorkItem $record): string => (string) ($record->categoryLabel() ?? __('work_item.values.no_category')))
            ->collapsible();
        $groups[] = Group::make('status')
            ->label(__('work_item.table.status'))
            ->getTitleFromRecordUsing(fn (WorkItem $record): string => (string) $record->status?->getLabel())
            ->collapsible();

        return $groups;
    }

    /** Toplu islem: durum, proje, kritik. Yetkisi olmayan kartlar atlanir. */
    private static function bulkActions(): BulkActionGroup
    {
        return BulkActionGroup::make([
            BulkAction::make('changeStatus')
                ->label(__('work_item.actions.change_status'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->schema([
                    Select::make('status')
                        ->label(__('work_item.fields.status'))
                        ->options(WorkItemStatus::class)
                        ->required()
                        ->live()
                        ->native(false),
                    Select::make('waiting_kind')
                        ->label(__('work_item.fields.waiting_kind'))
                        ->options(WorkWaitingKind::class)
                        ->live()
                        ->native(false)
                        ->visible(fn (Get $get): bool => self::statusValue($get('status')) === WorkItemStatus::Waiting->value)
                        ->required(fn (Get $get): bool => self::statusValue($get('status')) === WorkItemStatus::Waiting->value),
                    Select::make('waiting_personnel_id')
                        ->label(__('work_item.fields.waiting_personnel'))
                        ->tap(PersonnelSelect::all())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => self::statusValue($get('status')) === WorkItemStatus::Waiting->value && self::statusValue($get('waiting_kind')) === WorkWaitingKind::Personnel->value),
                    Select::make('waiting_party_id')
                        ->label(__('work_item.fields.waiting_party'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => app(WorkItemQueries::class)->partyOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? (app(WorkItemQueries::class)->partyOptions('', (int) $value)[(int) $value] ?? null) : null)
                        ->native(false)
                        ->visible(fn (Get $get): bool => self::statusValue($get('status')) === WorkItemStatus::Waiting->value && self::statusValue($get('waiting_kind')) === WorkWaitingKind::Party->value),
                    TextInput::make('waiting_text')
                        ->label(__('work_item.fields.waiting_text'))
                        ->maxLength(200)
                        ->visible(fn (Get $get): bool => self::statusValue($get('status')) === WorkItemStatus::Waiting->value && self::statusValue($get('waiting_kind')) === WorkWaitingKind::Text->value),
                ])
                ->action(fn (Collection $records, array $data) => self::runBulk($records, fn (WorkItem $record) => app(WorkItemService::class)->changeStatus($record, self::statusValue($data['status']), $data)))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('assignProject')
                ->label(__('work_item.actions.assign_project'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->schema([
                    Select::make('project_id')
                        ->label(__('work_item.fields.project'))
                        ->options(fn (): array => app(WorkItemQueries::class)->projectNames())
                        ->searchable()
                        ->placeholder(__('work_item.values.no_project'))
                        ->native(false),
                ])
                ->action(function (Collection $records, array $data): void {
                    $allowed = self::allowed($records);

                    if ($allowed === []) {
                        self::notifyNone();

                        return;
                    }

                    try {
                        $count = app(WorkItemService::class)->assignProject($allowed, filled($data['project_id'] ?? null) ? (int) $data['project_id'] : null);
                        self::notifyDone($count, count($records) - count($allowed));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                })
                ->deselectRecordsAfterCompletion(),
            // D-219: toplu kritiklik (dort seviye).
            BulkAction::make('setPriority')
                ->label(__('work_item.actions.set_priority'))
                ->icon(Heroicon::OutlinedFire)
                ->color('warning')
                ->visible(fn (): bool => WorkItemQueries::extendedUi())
                ->schema([
                    Select::make('priority')
                        ->label(__('work_item.fields.priority'))
                        ->options(WorkItemPriority::class)
                        ->required()
                        ->native(false),
                ])
                ->action(fn (Collection $records, array $data) => self::runBulk($records, fn (WorkItem $record) => app(WorkItemService::class)->setPriority($record, self::statusValue($data['priority']))))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('markCritical')
                ->label(__('work_item.actions.mark_critical'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                // Kirmizi yalniz silme icindir (14 Ekim 2026).
                ->color('warning')
                ->action(fn (Collection $records) => self::runBulk($records, fn (WorkItem $record) => app(WorkItemService::class)->setCritical($record, true)))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('unmarkCritical')
                ->label(__('work_item.actions.unmark_critical'))
                ->icon(Heroicon::OutlinedMinusCircle)
                ->color('gray')
                ->action(fn (Collection $records) => self::runBulk($records, fn (WorkItem $record) => app(WorkItemService::class)->setCritical($record, false)))
                ->deselectRecordsAfterCompletion(),
        ])->label(__('work_item.actions.bulk'));
    }

    /** Personel sutununun alt satiri (D-219): diger sorumlular ya da Tum ekip. */
    private static function sharedLine(WorkItem $record): ?string
    {
        if (! WorkItemQueries::extendedUi()) {
            return null;
        }

        $parts = [];

        if ((bool) $record->getAttribute('for_everyone')) {
            $parts[] = __('work_item.values.for_everyone');
        }

        if ($record->relationLoaded('assignees') && $record->assignees->isNotEmpty()) {
            $parts[] = '+ '.$record->assignees->pluck('full_name')->implode(', ');
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @return array<string, string>
     */
    public static function rangeOptions(): array
    {
        $options = [];

        foreach (WorkItemQueries::RANGES as $range) {
            $options[$range] = (string) __('work_item.ranges.'.$range);
        }

        return $options;
    }

    private static function statusValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * @param  Collection<int, WorkItem>  $records
     */
    private static function runBulk(Collection $records, callable $operation): void
    {
        $done = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! Gate::allows('update', $record)) {
                $skipped++;

                continue;
            }

            try {
                $operation($record);
                $done++;
            } catch (AbstractException $exception) {
                DomainNotifications::failure($exception);

                return;
            }
        }

        $done > 0 ? self::notifyDone($done, $skipped) : self::notifyNone();
    }

    /**
     * @param  Collection<int, WorkItem>  $records
     * @return list<int>
     */
    private static function allowed(Collection $records): array
    {
        return $records
            ->filter(fn (WorkItem $record): bool => Gate::allows('update', $record))
            ->map(fn (WorkItem $record): int => (int) $record->getKey())
            ->values()
            ->all();
    }

    private static function notifyDone(int $done, int $skipped): void
    {
        Notification::make()
            ->success()
            ->title(__('work_item.notifications.bulk_done', ['count' => $done]))
            ->body($skipped > 0 ? __('work_item.notifications.bulk_skipped', ['count' => $skipped]) : null)
            ->send();
    }

    private static function notifyNone(): void
    {
        Notification::make()
            ->warning()
            ->title(__('work_item.notifications.bulk_none'))
            ->send();
    }
}
