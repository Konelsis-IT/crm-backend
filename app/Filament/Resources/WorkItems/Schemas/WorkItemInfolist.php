<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Schemas;

use App\Enums\Report\WorkItemStatus;
use App\Models\Activity\PersonnelActivity;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\WorkItemPresenter;
use App\Support\ActivityLabels;
use App\Support\DisplayTime;
use App\Support\WorkDurationFormat;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Kart detayi (B36, D-115): kartin butun alanlari, durum gecmisinden
 * hesaplanan sureler ve Personel Hareketleri'ndeki gecmisi.
 */
final class WorkItemInfolist
{
    public static function make(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 3])
                ->extraAttributes(['class' => 'kc-grid-top'])
                ->components([
                    Section::make(__('work_item.sections.main'))
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->columnSpan(['default' => 1, 'xl' => 2])
                        ->columns(['default' => 1, 'md' => 3])
                        ->components([
                            TextEntry::make('title')->label(__('work_item.fields.title'))->weight('semibold')->columnSpanFull(),
                            TextEntry::make('status')->label(__('work_item.fields.status'))->badge(),
                            TextEntry::make('is_critical')
                                ->label(__('work_item.fields.is_critical'))
                                ->formatStateUsing(fn (bool $state): string => $state ? __('work_item.values.critical') : __('work_item.values.no'))
                                ->badge()
                                ->color(fn (bool $state): string => $state ? 'danger' : 'gray'),
                            TextEntry::make('source')->label(__('work_item.fields.source'))->badge(),
                            TextEntry::make('personnel.full_name')->label(__('work_item.fields.personnel'))->icon(Heroicon::OutlinedUser),
                            TextEntry::make('orgUnit.name')->label(__('work_item.fields.org_unit'))->placeholder('–'),
                            TextEntry::make('category_label')
                                ->label(__('work_item.fields.category'))
                                ->state(fn (WorkItem $record): ?string => $record->categoryLabel())
                                ->placeholder('–'),
                            TextEntry::make('project.name')->label(__('work_item.fields.project'))->icon(Heroicon::OutlinedBriefcase)->placeholder('–'),
                            TextEntry::make('parent.title')->label(__('work_item.fields.parent'))->placeholder('–'),
                            TextEntry::make('requester')
                                ->label(__('work_item.fields.requester'))
                                ->state(fn (WorkItem $record): ?string => $record->requesterLabel())
                                ->placeholder('–')
                                ->visible(fn (): bool => SchemaReadiness::hasBatch('B37')),
                            TextEntry::make('work_at')->label(__('work_item.fields.work_at'))->dateTime('d.m.Y H:i'),
                            TextEntry::make('due_on')->label(__('work_item.fields.due_on'))->date('d.m.Y')->placeholder('–'),
                            TextEntry::make('completed_at')->label(__('work_item.fields.completed_at'))->dateTime('d.m.Y H:i')->placeholder('–'),
                            TextEntry::make('work_hours')
                                ->label(__('work_item.fields.work_hours'))
                                ->formatStateUsing(fn ($state): ?string => WorkDurationFormat::hours($state !== null ? (float) $state : null))
                                ->placeholder('–'),
                            TextEntry::make('waiting_label')
                                ->label(__('work_item.fields.waiting'))
                                ->state(fn (WorkItem $record): ?string => $record->waitingLabel() === null ? null : (
                                    $record->status === WorkItemStatus::Waiting
                                        ? __('work_item.values.waiting_short', ['who' => $record->waitingLabel(), 'days' => (int) $record->waitingDays()])
                                        : $record->waitingLabel()
                                ))
                                ->color(fn (WorkItem $record): string => $record->isLongWaiting() ? 'warning' : 'gray')
                                ->placeholder('–'),
                            TextEntry::make('link_label')
                                ->label(__('work_item.fields.link'))
                                ->state(function (WorkItem $record): ?string {
                                    $link = app(WorkItemPresenter::class)->link($record);

                                    return $link === null ? null : $link['kind_label'].' · '.trim(($link['no'] !== null ? $link['no'].' ' : '').$link['label']);
                                })
                                ->url(fn (WorkItem $record): ?string => app(WorkItemPresenter::class)->link($record)['url'] ?? null)
                                ->color('primary')
                                ->placeholder('–')
                                ->columnSpan(['default' => 1, 'md' => 2]),
                            TextEntry::make('note')->label(__('work_item.fields.note'))->placeholder('–')->columnSpanFull(),
                        ]),
                    Section::make(__('work_item.sections.durations'))
                        ->icon(Heroicon::OutlinedClock)
                        ->description(__('work_item.help.durations'))
                        ->columns(2)
                        ->components([
                            TextEntry::make('total_days')
                                ->label(__('work_item.duration.columns.total'))
                                ->state(fn (WorkItem $record): ?string => WorkDurationFormat::days($record->totalDays())),
                            TextEntry::make('progress_span')
                                ->label(__('work_item.duration.columns.progress'))
                                ->state(fn (WorkItem $record): ?string => WorkDurationFormat::span($record->secondsIn('progress')))
                                ->placeholder('–'),
                            TextEntry::make('waiting_span')
                                ->label(__('work_item.duration.columns.waiting'))
                                ->state(fn (WorkItem $record): ?string => WorkDurationFormat::span($record->secondsIn('waiting')))
                                ->placeholder('–'),
                            TextEntry::make('blocked_span')
                                ->label(__('work_item.duration.columns.blocked'))
                                ->state(fn (WorkItem $record): ?string => WorkDurationFormat::span($record->secondsIn('blocked')))
                                ->placeholder('–'),
                            TextEntry::make('due_deviation')
                                ->label(__('work_item.duration.columns.deviation'))
                                ->state(fn (WorkItem $record): ?string => WorkDurationFormat::deviation($record->dueDeviation(), $record->completed_at !== null, $record->due_on)[0])
                                ->color(fn (WorkItem $record): string => WorkDurationFormat::deviation($record->dueDeviation(), $record->completed_at !== null, $record->due_on)[1])
                                ->placeholder('–')
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make(__('work_item.sections.history'))
                ->icon(Heroicon::OutlinedClock)
                ->collapsible()
                ->components([
                    RepeatableEntry::make('history')
                        ->hiddenLabel()
                        ->state(fn (WorkItem $record): array => app(WorkItemQueries::class)
                            ->activitiesFor((int) $record->getKey())
                            ->map(fn (PersonnelActivity $activity): array => [
                                'at' => DisplayTime::format($activity->occurred_at),
                                'by' => $activity->actorName(),
                                'action' => ActivityLabels::action($activity->action_code),
                                'changes' => implode(' | ', ActivityLabels::changeLines($activity->changes)),
                            ])
                            ->all())
                        ->table([
                            TableColumn::make(__('work_item.history.at')),
                            TableColumn::make(__('work_item.history.by')),
                            TableColumn::make(__('work_item.history.action')),
                            TableColumn::make(__('work_item.history.changes')),
                        ])
                        ->schema([
                            TextEntry::make('at'),
                            TextEntry::make('by'),
                            TextEntry::make('action')->weight('medium'),
                            TextEntry::make('changes')->color('gray')->placeholder('–'),
                        ])
                        ->placeholder(__('work_item.history.empty')),
                ]),
        ]);
    }
}
