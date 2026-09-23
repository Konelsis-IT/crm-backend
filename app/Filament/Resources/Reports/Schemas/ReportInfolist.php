<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Schemas;

use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportSubjectKind;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\CardGallery;
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
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Rapor karti (D-86): kimlik/konu/donem, taslagin cevaplari, pano tipli
 * raporda durum sekmeleri (her sekmede isler tablo halinde), sayisal ozet
 * (KPI), inceleme ve gecmis.
 */
final class ReportInfolist
{
    public static function make(Schema $schema): Schema
    {
        /** @var Report $report */
        $report = $schema->getRecord();
        $template = $report->template();

        // Ust bolum: personel ayrintisindaki gibi genis kart + dar yan kutu.
        $components = [
            Grid::make(['default' => 1, 'lg' => 4])->components([
                app(CardGallery::class)
                    ->reportDetailCard($report, self::subjectUrl($report))
                    ->columnSpan(['default' => 1, 'lg' => 3]),
                Section::make(__('report.sections.side'))
                    ->icon(Heroicon::OutlinedClock)
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->components([
                        TextEntry::make('submitted_at')
                            ->label(__('report.fields.submitted_at'))
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('reviewer.full_name')
                            ->label(__('report.fields.reviewer'))
                            ->icon(Heroicon::OutlinedUser)
                            ->placeholder(__('report.values.no_reviewer')),
                        TextEntry::make('reviewed_at')
                            ->label(__('report.fields.reviewed_at'))
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-')
                            ->visible(fn (Report $record): bool => $record->reviewed_at !== null),
                        TextEntry::make('revision_count')
                            ->label(__('report.fields.revision_count'))
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->visible(fn (Report $record): bool => (int) $record->revision_count > 0),
                    ]),
            ]),
        ];

        // Cevaplar genis (3/4), sayisal ozet dar (1/4) - ust karttaki duzenle ayni.
        $hasMetrics = $report->metrics->isNotEmpty() && $template !== null;

        $answers = Section::make($template?->name() ?? __('report.sections.answers'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->columns(FieldGrid::COLUMNS)
            ->columnSpan($hasMetrics ? ['default' => 1, 'lg' => 3] : ['default' => 1, 'lg' => 4])
            ->components($template !== null
                ? FieldGrid::fields($template->infolistEntries())
                : [TextEntry::make('missing_template')->label(__('report.fields.template'))->hiddenLabel()->state(__('report.values.template_missing'))->color('danger')]);

        $metrics = $hasMetrics
            ? Section::make(__('report.sections.metrics'))
                ->icon(Heroicon::OutlinedChartBar)
                ->columnSpan(['default' => 1, 'lg' => 1])
                ->components($report->metrics->map(fn (ReportMetric $metric): TextEntry => TextEntry::make('metric_'.$metric->metric_code)
                    ->label($template->metricLabel((string) $metric->metric_code))
                    ->state(ReportFieldComponents::formatNumber($metric->metric_value, $metric->unit))
                    ->badge()
                    ->color('info'))->all())
            : null;

        $components[] = Grid::make(['default' => 1, 'lg' => 4])
            ->components(array_values(array_filter([$answers, $metrics])));

        if ($template?->hasItems()) {
            $components[] = self::itemsSection($report);
        }

        $components[] = Section::make(__('report.sections.review'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->visible(fn (Report $record): bool => filled($record->review_comment))
            ->components([
                TextEntry::make('review_comment')->label(__('report.fields.review_comment'))->hiddenLabel()->placeholder('-')->columnSpanFull(),
            ]);

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

    /**
     * Isler: her durum bir sekme, sekmede o durumun isleri tablo halinde
     * (kullanici istegi, 23 Eylul 2026; onceki sutunlu pano kaldirildi).
     */
    private static function itemsSection(Report $report): Section
    {
        $tabs = [];

        foreach (ReportItemStatus::available() as $status) {
            $rows = $report->items->where('status', $status);

            $tabs[] = Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->badge($rows->count() ?: null)
                ->badgeColor($status->getColor())
                ->schema([
                    RepeatableEntry::make('items_'.$status->value)
                        ->hiddenLabel()
                        ->state(fn (Report $record): array => $record->items
                            ->where('status', $status)
                            ->map(fn (ReportItem $item): array => [
                                'title' => (string) $item->title,
                                'project' => $item->project?->name,
                                'hours' => ReportFieldComponents::formatNumber($item->work_hours, __('report.values.hours')),
                                'description' => $item->description,
                                'tag' => self::itemTag($item),
                            ])
                            ->values()
                            ->all())
                        ->table([
                            TableColumn::make(__('report.items.title')),
                            TableColumn::make(__('report.items.project')),
                            TableColumn::make(__('report.items.work_hours')),
                            TableColumn::make(__('report.items.description')),
                            TableColumn::make(__('report.items.tag')),
                        ])
                        ->schema([
                            TextEntry::make('title')->weight('medium'),
                            TextEntry::make('project')->color('gray')->placeholder('–'),
                            TextEntry::make('hours')->color('gray')->placeholder('–'),
                            TextEntry::make('description')->color('gray')->placeholder('–'),
                            TextEntry::make('tag')->badge()->color('warning')->placeholder('–'),
                        ])
                        ->placeholder(__('report.values.no_items')),
                ]);
        }

        return Section::make(__('report.sections.board'))
            ->icon(Heroicon::OutlinedListBullet)
            ->components([
                Tabs::make('report-items')->id('report-items')->persistTab()->contained(false)->tabs($tabs),
            ]);
    }

    /** Devreden / sonradan eklenen kalem isareti. */
    private static function itemTag(ReportItem $item): ?string
    {
        if ((bool) $item->getAttribute('is_late')) {
            return __('report.values.added_late');
        }

        return $item->carried_from_item_id !== null ? __('report.values.carried_over') : null;
    }
}
