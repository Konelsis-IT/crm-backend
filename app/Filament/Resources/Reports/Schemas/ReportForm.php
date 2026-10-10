<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Schemas;

use App\Enums\Report\ReportAuthorRule;
use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportSubjectKind;
use App\Filament\Forms\Components\CardPicker;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Report\ReportQueries;
use App\Reports\ReportTemplate;
use App\Reports\ReportTemplateRegistry;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\ReportSuggestions;
use App\Support\DisplayTime;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\Str;

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
                    // Taslak secimi: ikonlu kucuk kartlar (kullanici istegi,
                    // 23 Eylul 2026). Secilen taslagin alanlari asagida acilir.
                    // Var olan rapor duzenlenirken kendi taslagi listede kalir;
                    // yeni raporda yalniz elle yazilabilen taslaklar secilir (D-117).
                    CardPicker::make('template_code')
                        ->label(__('report.fields.template'))
                        ->cards(fn (?Report $record): array => $viewer === null ? [] : array_map(
                            fn (ReportTemplate $candidate): array => [
                                'value' => $candidate->code(),
                                'label' => $candidate->name(),
                                'description' => $candidate->description(),
                                'icon' => $candidate->icon(),
                            ],
                            array_values(array_filter(
                                $registry->all(),
                                fn (ReportTemplate $candidate): bool => ($record !== null && $candidate->code() === $record->template_code)
                                    || $queries->canAuthorTemplate($candidate, $viewer),
                            )),
                        ))
                        ->required()
                        ->live()
                        ->disabled(fn (?Report $record): bool => $record !== null)
                        ->dehydrated()
                        ->afterStateUpdated(function (Set $set, Get $get) use ($template): void {
                            $current = $template($get);
                            $periodStart = $current !== null && $current->periodMode()->isCalendarUnit() ? Carbon::today(DisplayTime::zone())->format('Y-m-d') : null;

                            $set('period_start', $periodStart);
                            $set('period_end', null);

                            // D-167: gunluk / haftalik raporda donemin isleri, gorusme
                            // notlari ve yazilan raporlar oneri olarak (isaretli) gelir.
                            $prefill = $current !== null
                                ? app(ReportSuggestions::class)->prefill($current, (int) auth()->id(), $periodStart)
                                : ['payload' => [], 'items' => null];

                            $set('payload', $prefill['payload']);
                            $set('items', self::keyedRows($prefill['items'] ?? []));
                        })
                        ->columnSpanFull(),
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
                        ->required(fn (Get $get): bool => $template($get)?->periodRequired() ?? false)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set, ?Report $record, mixed $state, mixed $old) => self::refreshSuggestions($template($get), $get, $set, $record, $state, $old)),
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
            // D-179: panodan is secme kutusu ve tek tikla eklenen oneriler,
            // is kalemlerinin ustunde (yalniz is panosu kullanan taslaklarda).
            Section::make(__('report.sections.board'))
                ->description(__('report.help.board'))
                ->icon(Heroicon::OutlinedViewColumns)
                ->columns(FieldGrid::COLUMNS)
                ->visible(fn (Get $get): bool => $template($get)?->hasItems() ?? false)
                ->components([...ReportWorkPicker::components($template), self::itemsRepeater()]),
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

    /**
     * Donem degisince onerileri yeniler (D-167): kaynak alanlarinda yeni
     * donemin butun onerileri isaretli gelir; is panosu kalemleri yeni
     * donemin kartlariyla degisir, elle eklenen satirlar kalir. Ayni donem
     * icinde gun degisirse (haftalik raporda) secimlere dokunulmaz.
     */
    private static function refreshSuggestions(?ReportTemplate $template, Get $get, Set $set, ?Report $record, mixed $state, mixed $old): void
    {
        $suggestions = app(ReportSuggestions::class);

        if ($template === null || ! $suggestions->supports($template)) {
            return;
        }

        $period = $suggestions->period($template, $state);
        $previous = $suggestions->period($template, $old);

        if ($period === null || ($previous !== null && $period[0]->equalTo($previous[0]) && $period[1]->equalTo($previous[1]))) {
            return;
        }

        $authorId = $record instanceof Report ? (int) $record->author_personnel_id : (int) auth()->id();
        $prefill = $suggestions->prefill($template, $authorId, $state);

        foreach ($prefill['payload'] as $name => $keys) {
            $set('payload.'.$name, $keys);
        }

        if ($suggestions->usesBoard($template)) {
            // D-179: panodan secilen ya da oneriden eklenen kart, yeni donemin
            // kartlari arasinda yoksa elle yazilan satirlar gibi korunur.
            $prefillIds = ReportWorkPicker::linkedIds($prefill['items'] ?? []);
            $manual = array_filter(
                is_array($get('items')) ? $get('items') : [],
                static fn ($row): bool => is_array($row) && (
                    (blank($row['work_item_id'] ?? null) && blank($row['carried_from_item_id'] ?? null))
                    || ((bool) ($row['picked'] ?? false) && ! in_array((int) $row['work_item_id'], $prefillIds, true))
                ),
            );

            $set('items', [...self::keyedRows($prefill['items'] ?? []), ...$manual]);
        }
    }

    /**
     * Repeater durumu: her satir kendi anahtariyla (Filament tekrarlayici bicimi).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private static function keyedRows(array $rows): array
    {
        $keyed = [];

        foreach ($rows as $row) {
            $keyed[(string) Str::uuid()] = $row;
        }

        return $keyed;
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
                // Is panosundan gelen kalemin kaynak karti (B36; D-167 Rapor yaz onerisi).
                Hidden::make('work_item_id'),
                Hidden::make('is_late'),
                // D-179: panodan secilen / oneriden eklenen satir (yalniz formda).
                Hidden::make('picked')->dehydrated(false),
                TextInput::make('title')
                    ->label(__('report.items.title'))
                    ->required()
                    ->maxLength(200),
                Select::make('status')
                    ->label(__('report.items.status'))
                    ->options(ReportItemStatus::availableOptions())
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
