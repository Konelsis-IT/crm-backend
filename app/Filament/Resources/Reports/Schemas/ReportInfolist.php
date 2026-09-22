<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Schemas;

use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportSubjectKind;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\FieldGrid;
use App\Models\Activity\PersonnelActivity;
use App\Models\Report\Report;
use App\Models\Report\ReportItem;
use App\Models\Report\ReportMetric;
use App\Query\Report\ReportQueries;
use App\Reports\ReportFieldComponents;
use App\Support\ActivityLabels;
use App\Support\DisplayTime;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Rapor karti (D-86): kimlik/konu/donem, taslagin cevaplari, pano tipli
 * raporda durum sutunlu is panosu, sayisal ozet (KPI), inceleme ve gecmis.
 */
final class ReportInfolist
{
    public static function make(Schema $schema): Schema
    {
        /** @var Report $report */
        $report = $schema->getRecord();
        $template = $report->template();

        $components = [
            Section::make(__('report.sections.report'))
                ->icon(Heroicon::OutlinedDocumentChartBar)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('report_no')->label(__('report.fields.report_no'))->badge()->color('gray'),
                    TextEntry::make('status')->label(__('report.fields.status'))->badge(),
                    TextEntry::make('kind')->label(__('report.fields.kind'))->badge(),
                    TextEntry::make('template_name')
                        ->label(__('report.fields.template'))
                        ->state(fn (Report $record): string => $record->templateName()),
                    TextEntry::make('author.full_name')->label(__('report.fields.author'))->icon(Heroicon::OutlinedUserCircle),
                    TextEntry::make('authorOrgUnit.name')->label(__('report.fields.author_org_unit'))->placeholder('-'),
                    self::subjectEntry(),
                    TextEntry::make('period')
                        ->label(__('report.fields.period'))
                        ->state(fn (Report $record): ?string => $record->periodLabel())
                        ->placeholder('-')
                        ->icon(Heroicon::OutlinedCalendarDays),
                    TextEntry::make('submitted_at')->label(__('report.fields.submitted_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                    TextEntry::make('reviewer.full_name')->label(__('report.fields.reviewer'))->placeholder(__('report.values.no_reviewer')),
                    TextEntry::make('revision_count')
                        ->label(__('report.fields.revision_count'))
                        ->visible(fn (Report $record): bool => (int) $record->revision_count > 0),
                    TextEntry::make('is_confidential')
                        ->label(__('report.fields.confidential'))
                        ->state(fn (): string => __('report.values.confidential'))
                        ->badge()
                        ->color('danger')
                        ->icon(Heroicon::OutlinedLockClosed)
                        ->visible(fn (Report $record): bool => $record->is_confidential),
                ])),
        ];

        $components[] = Section::make($template?->name() ?? __('report.sections.answers'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->columns(FieldGrid::COLUMNS)
            ->components($template !== null
                ? FieldGrid::fields($template->infolistEntries())
                : [TextEntry::make('missing_template')->label(__('report.fields.template'))->hiddenLabel()->state(__('report.values.template_missing'))->color('danger')]);

        if ($template?->hasItems()) {
            $components[] = self::boardSection();
        }

        if ($report->metrics->isNotEmpty() && $template !== null) {
            $components[] = Section::make(__('report.sections.metrics'))
                ->description(__('report.help.metrics'))
                ->icon(Heroicon::OutlinedChartBar)
                ->columns(FieldGrid::COLUMNS)
                ->components($report->metrics->map(fn (ReportMetric $metric): TextEntry => TextEntry::make('metric_'.$metric->metric_code)
                    ->label($template->metricLabel((string) $metric->metric_code))
                    ->state(ReportFieldComponents::formatNumber($metric->metric_value, $metric->unit))
                    ->badge()
                    ->color('info')
                    ->columnSpan(FieldGrid::SHORT))->all());
        }

        $components[] = Section::make(__('report.sections.review'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->columns(FieldGrid::COLUMNS)
            ->visible(fn (Report $record): bool => $record->reviewed_at !== null)
            ->components(FieldGrid::fields([
                TextEntry::make('reviewer.full_name')->label(__('report.fields.reviewer'))->placeholder('-'),
                TextEntry::make('reviewed_at')->label(__('report.fields.reviewed_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                TextEntry::make('review_comment')->label(__('report.fields.review_comment'))->placeholder('-')->columnSpanFull(),
            ]));

        $components[] = Section::make(__('report.sections.history'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->collapsible()
            ->collapsed()
            ->components([
                TextEntry::make('history')
                    ->label(__('report.sections.history'))
                    ->hiddenLabel()
                    ->listWithLineBreaks()
                    ->state(fn (Report $record): array => app(ReportQueries::class)
                        ->activitiesFor((int) $record->getKey())
                        ->map(fn (PersonnelActivity $activity): string => sprintf(
                            '%s · %s · %s%s',
                            $activity->occurred_at?->timezone(DisplayTime::zone())->format('d.m.Y H:i') ?? '-',
                            $activity->actorName(),
                            ActivityLabels::action($activity->action_code),
                            ($lines = ActivityLabels::changeLines($activity->changes)) !== [] ? ' — '.implode(' | ', $lines) : '',
                        ))
                        ->all())
                    ->placeholder(__('report.values.no_history')),
            ]);

        return $schema->columns(1)->components($components);
    }

    /** Bagli kayit; kaydin goruntuleme sayfasina baglanti. */
    private static function subjectEntry(): TextEntry
    {
        return TextEntry::make('subject')
            ->label(fn (Report $record): string => $record->subject_kind === ReportSubjectKind::None
                ? __('report.fields.subject')
                : __('report.fields.subject_'.$record->subject_kind->value))
            ->state(fn (Report $record): ?string => $record->subjectLabel())
            ->placeholder('-')
            ->icon(fn (Report $record): Heroicon => $record->subject_kind->getIcon())
            ->color('primary')
            ->url(fn (Report $record): ?string => self::subjectUrl($record))
            ->visible(fn (Report $record): bool => $record->subject_kind !== ReportSubjectKind::None);
    }

    private static function subjectUrl(Report $record): ?string
    {
        $subject = $record->subject();

        if (! $subject instanceof Model) {
            return null;
        }

        $resource = match ($record->subject_kind) {
            ReportSubjectKind::Personnel => PersonnelResource::class,
            ReportSubjectKind::Project => ProjectResource::class,
            ReportSubjectKind::Proposal => ProposalResource::class,
            ReportSubjectKind::BusinessCase => BusinessCaseResource::class,
            default => null,
        };

        if ($resource === null) {
            return null;
        }

        try {
            return $resource::getUrl('view', ['record' => $subject]);
        } catch (Throwable) {
            return null;
        }
    }

    /** Is panosu: her durum bir sutun. */
    private static function boardSection(): Section
    {
        $columns = [];

        foreach (ReportItemStatus::cases() as $status) {
            $columns[] = Section::make($status->getLabel())
                ->icon($status->getIcon())
                ->compact()
                ->components([
                    TextEntry::make('count_'.$status->value)
                        ->label($status->getLabel())
                        ->hiddenLabel()
                        ->state(fn (Report $record): string => __('report.values.item_count', ['count' => $record->items->where('status', $status)->count()]))
                        ->badge()
                        ->color($status->getColor()),
                    RepeatableEntry::make('items_'.$status->value)
                        ->label($status->getLabel())
                        ->hiddenLabel()
                        ->state(fn (Report $record): array => $record->items->where('status', $status)->values()->all())
                        ->placeholder(__('report.values.no_items'))
                        ->schema([
                            TextEntry::make('title')->label(__('report.items.title'))->hiddenLabel()->weight('semibold'),
                            TextEntry::make('project.name')
                                ->label(__('report.items.project'))
                                ->hiddenLabel()
                                ->icon(Heroicon::OutlinedBriefcase)
                                ->color('gray')
                                ->visible(fn (ReportItem $record): bool => $record->project_id !== null),
                            TextEntry::make('work_hours')
                                ->label(__('report.items.work_hours'))
                                ->hiddenLabel()
                                ->icon(Heroicon::OutlinedClock)
                                ->color('gray')
                                ->formatStateUsing(fn ($state): ?string => ReportFieldComponents::formatNumber($state, __('report.values.hours')))
                                ->visible(fn (ReportItem $record): bool => $record->work_hours !== null),
                            TextEntry::make('description')
                                ->label(__('report.items.description'))
                                ->hiddenLabel()
                                ->color('gray')
                                ->visible(fn (ReportItem $record): bool => filled($record->description)),
                            TextEntry::make('carried')
                                ->label(__('report.values.carried_over'))
                                ->hiddenLabel()
                                ->state(fn (): string => __('report.values.carried_over'))
                                ->badge()
                                ->color('warning')
                                ->visible(fn (ReportItem $record): bool => $record->carried_from_item_id !== null),
                        ]),
                ]);
        }

        return Section::make(__('report.sections.board'))
            ->icon(Heroicon::OutlinedViewColumns)
            ->components([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->components($columns),
            ]);
    }
}
