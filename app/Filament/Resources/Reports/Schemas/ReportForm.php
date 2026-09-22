<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Schemas;

use App\Enums\Report\ReportAuthorRule;
use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportSubjectKind;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Report\ReportQueries;
use App\Reports\ReportTemplate;
use App\Reports\ReportTemplateRegistry;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Rapor formu (D-86): once taslak secilir; taslak, konu alanini, donem
 * alanlarini, cevap bolumunu (payload) ve varsa is panosunu belirler.
 * Duzenlemede taslak kilitlidir.
 */
final class ReportForm
{
    public static function make(Schema $schema): Schema
    {
        $registry = app(ReportTemplateRegistry::class);
        $queries = app(ReportQueries::class);
        $user = auth()->user();
        $viewer = $user instanceof Personnel ? $user : null;
        $template = static fn (Get $get): ?ReportTemplate => $registry->find((string) $get('template_code'));
        $mode = static fn (Get $get): ReportPeriodMode => $template($get)?->periodMode() ?? ReportPeriodMode::None;

        return $schema->columns(1)->components([
            Section::make(__('report.sections.report'))
                ->description(__('report.help.report'))
                ->icon(Heroicon::OutlinedDocumentChartBar)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('template_code')
                        ->label(__('report.fields.template'))
                        ->options(fn (): array => $viewer !== null
                            ? $registry->options(fn (ReportTemplate $candidate): bool => $queries->canAuthorTemplate($candidate, $viewer))
                            : [])
                        ->required()
                        ->live()
                        ->native(false)
                        ->disabled(fn (?Report $record): bool => $record !== null)
                        ->dehydrated()
                        ->afterStateUpdated(function (Set $set, Get $get) use ($template): void {
                            $current = $template($get);

                            $set('payload', []);
                            $set('items', []);
                            $set('period_start', $current !== null && $current->periodMode()->isCalendarUnit() ? Carbon::today(DisplayTime::zone())->format('Y-m-d') : null);
                            $set('period_end', null);
                        })
                        ->columnSpan(FieldGrid::NORMAL),
                    Placeholder::make('template_help')
                        ->label(__('report.fields.template_help'))
                        ->content(fn (Get $get): string => (string) ($template($get)?->description() ?? ''))
                        ->visible(fn (Get $get): bool => filled($template($get)?->description()))
                        ->columnSpan(FieldGrid::HALF),
                    TextInput::make('title')
                        ->label(__('report.fields.title'))
                        ->maxLength(200)
                        ->helperText(__('report.help.title'))
                        ->columnSpan(FieldGrid::HALF),
                    ...self::subjectSelects($template, $viewer, $queries),
                    DatePicker::make('period_start')
                        ->label(fn (Get $get): string => __('report.fields.period_'.$mode($get)->value))
                        ->helperText(fn (Get $get): ?string => match ($mode($get)) {
                            ReportPeriodMode::Week => __('report.help.period_week'),
                            ReportPeriodMode::Month => __('report.help.period_month'),
                            default => null,
                        })
                        ->visible(fn (Get $get): bool => $mode($get) !== ReportPeriodMode::None)
                        ->required(fn (Get $get): bool => $template($get)?->periodRequired() ?? false),
                    DatePicker::make('period_end')
                        ->label(__('report.fields.period_end'))
                        ->visible(fn (Get $get): bool => $mode($get) === ReportPeriodMode::Range)
                        ->required(fn (Get $get): bool => $mode($get) === ReportPeriodMode::Range
                            && (($template($get)?->periodRequired() ?? false) || filled($get('period_start'))))
                        ->afterOrEqual('period_start'),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
            Section::make(fn (Get $get): string => $template($get)?->name() ?? __('report.sections.answers'))
                ->description(fn (Get $get): ?string => $template($get) !== null ? __('report.help.answers') : null)
                ->icon(Heroicon::OutlinedPencilSquare)
                ->columns(FieldGrid::COLUMNS)
                ->statePath('payload')
                ->visible(fn (Get $get): bool => $template($get) !== null)
                ->components(fn (Get $get): array => ($current = $template($get)) !== null
                    ? FieldGrid::fields($current->formComponents())
                    : []),
            Section::make(__('report.sections.board'))
                ->description(__('report.help.board'))
                ->icon(Heroicon::OutlinedViewColumns)
                ->visible(fn (Get $get): bool => $template($get)?->hasItems() ?? false)
                ->components([self::itemsRepeater()]),
        ]);
    }

    /**
     * Konu secimleri: her konu turu icin bir Select; yalniz taslagin turu gorunur.
     *
     * @return array<int, Select>
     */
    private static function subjectSelects(Closure $template, ?Personnel $viewer, ReportQueries $queries): array
    {
        $selects = [];

        foreach (ReportSubjectKind::cases() as $kind) {
            $column = $kind->column();

            if ($column === null) {
                continue;
            }

            $matches = static fn (Get $get): bool => $template($get)?->subjectKind() === $kind;

            $selects[] = Select::make($column)
                ->label(__('report.fields.subject_'.$kind->value))
                ->options(fn (Get $get): array => $viewer !== null
                    ? $queries->subjectOptions($kind, $viewer, $template($get)?->authorRule() === ReportAuthorRule::SubjectManager)
                    : [])
                ->searchable()
                ->native(false)
                ->visible($matches)
                ->required($matches)
                ->columnSpan(FieldGrid::NORMAL);
        }

        return $selects;
    }

    /** Is panosu kalemleri (tablo bicimli tekrarlayici). */
    private static function itemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->label(__('report.fields.items'))
            ->table([
                TableColumn::make(__('report.items.title'))->markAsRequired(),
                TableColumn::make(__('report.items.status')),
                TableColumn::make(__('report.items.project')),
                TableColumn::make(__('report.items.work_hours')),
                TableColumn::make(__('report.items.description')),
            ])
            ->schema([
                Hidden::make('id'),
                Hidden::make('carried_from_item_id'),
                TextInput::make('title')
                    ->label(__('report.items.title'))
                    ->required()
                    ->maxLength(200),
                Select::make('status')
                    ->label(__('report.items.status'))
                    ->options(ReportItemStatus::class)
                    ->default(ReportItemStatus::Planned->value)
                    ->required()
                    ->native(false),
                Select::make('project_id')
                    ->label(__('report.items.project'))
                    ->options(fn (): array => SchemaReadiness::hasBatch('B17') ? app(ProjectCatalogQueries::class)->projectOptions() : [])
                    ->searchable()
                    ->native(false),
                TextInput::make('work_hours')
                    ->label(__('report.items.work_hours'))
                    ->numeric()
                    ->step(0.25)
                    ->minValue(0)
                    ->maxValue(999),
                TextInput::make('description')
                    ->label(__('report.items.description'))
                    ->maxLength(1000),
            ])
            ->addActionLabel(__('report.actions.add_item'))
            ->reorderable()
            ->defaultItems(0)
            ->columnSpanFull();
    }
}
