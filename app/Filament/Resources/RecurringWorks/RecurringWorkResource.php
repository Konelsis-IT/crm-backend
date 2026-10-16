<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringWorks;

use App\Enums\Platform\Feature;
use App\Enums\Report\RecurrenceFrequency;
use App\Enums\Report\RecurringWorkKind;
use App\Enums\Report\WorkItemPriority;
use App\Exceptions\AbstractException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\RecurringWorks\Pages\CreateRecurringWork;
use App\Filament\Resources\RecurringWorks\Pages\EditRecurringWork;
use App\Filament\Resources\RecurringWorks\Pages\ListRecurringWorks;
use App\Filament\Resources\RecurringWorks\Pages\ViewRecurringWork;
use App\Filament\Resources\RecurringWorks\RelationManagers\OccurrencesRelationManager;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\PersonnelSelect;
use App\Models\Personnel\Personnel;
use App\Models\Report\RecurringWork;
use App\Query\Report\RecurringWorkQueries;
use App\Query\Report\WorkItemQueries;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\RecurringWorkService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Raporlar > Gorev ve sorumluluklar (D-219, B57): personelin tekrarli isleri
 * (rapor kontrolu, mail kutusu, duzenli kontroller). Her donem sorumlunun
 * panosuna is acilir; listede son sekiz donemin uyumu (zamaninda / gec /
 * yapilmadi) gorunur. Kisi kendi gorevlerini, amir ekibinin, ust yonetim
 * hepsini gorur; tanimi amir ve ust yonetim yapar. Tanim silinmez, arsivlenir.
 */
class RecurringWorkResource extends Resource
{
    protected static ?string $model = RecurringWork::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'gorev-ve-sorumluluklar';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('recurring_work.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('recurring_work.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('recurring_work.nav');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::WorkRecurring) && SchemaReadiness::hasBatch('B57') && parent::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return app(RecurringWorkQueries::class)->applyVisible(
            parent::getEloquentQuery()->with(['personnel:id,full_name', 'project:id,name', 'orgUnit:id,name']),
            $user instanceof Personnel ? $user : null,
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(FieldGrid::group([
            TextInput::make('title')
                ->label(__('recurring_work.fields.title'))
                ->helperText(__('recurring_work.help.title'))
                ->required()
                ->maxLength(200)
                ->columnSpan(FieldGrid::WIDE),
            Select::make('personnel_id')
                ->label(__('recurring_work.fields.personnel'))
                ->tap(PersonnelSelect::active())
                ->required()
                ->searchable()
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            ToggleButtons::make('kind')
                ->label(__('recurring_work.fields.kind'))
                ->options(RecurringWorkKind::class)
                ->default(RecurringWorkKind::Report->value)
                ->inline()
                ->required()
                ->columnSpan(FieldGrid::HALF),
            ToggleButtons::make('frequency')
                ->label(__('recurring_work.fields.frequency'))
                ->options(RecurrenceFrequency::class)
                ->default(RecurrenceFrequency::Weekly->value)
                ->inline()
                ->live()
                ->required()
                ->columnSpan(FieldGrid::HALF),
            Select::make('weekday')
                ->label(__('recurring_work.fields.weekday'))
                ->helperText(__('recurring_work.help.weekday'))
                ->options(self::weekdays())
                ->placeholder(__('recurring_work.values.weekday_default'))
                ->native(false)
                ->visible(fn (Get $get): bool => self::frequency($get('frequency')) === RecurrenceFrequency::Weekly)
                ->columnSpan(FieldGrid::NORMAL),
            TextInput::make('month_day')
                ->label(__('recurring_work.fields.month_day'))
                ->helperText(__('recurring_work.help.month_day'))
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->visible(fn (Get $get): bool => self::frequency($get('frequency')) === RecurrenceFrequency::Monthly)
                ->columnSpan(FieldGrid::SHORT),
            ToggleButtons::make('priority')
                ->label(__('recurring_work.fields.priority'))
                ->options(WorkItemPriority::class)
                ->default(WorkItemPriority::Normal->value)
                ->inline()
                ->columnSpan(FieldGrid::HALF),
            Select::make('org_unit_id')
                ->label(__('recurring_work.fields.org_unit'))
                ->options(fn (): array => array_map(fn (array $unit): string => $unit['name'], app(WorkItemQueries::class)->units()))
                ->placeholder(__('work_item.values.unit_of_personnel'))
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('project_id')
                ->label(__('recurring_work.fields.project'))
                ->options(fn (): array => app(WorkItemQueries::class)->projectNames())
                ->searchable()
                ->placeholder(__('work_item.values.no_project'))
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('category_code')
                ->label(__('recurring_work.fields.category'))
                ->options(fn (): array => app(WorkCategoryCatalog::class)->allOptions())
                ->searchable()
                ->placeholder('–')
                ->native(false)
                ->columnSpan(FieldGrid::NORMAL),
            DatePicker::make('starts_on')
                ->label(__('recurring_work.fields.starts_on'))
                ->displayFormat('d.m.Y')
                ->default(fn (): string => RecurrenceFrequency::today()->toDateString())
                ->required(),
            DatePicker::make('ends_on')
                ->label(__('recurring_work.fields.ends_on'))
                ->helperText(__('recurring_work.help.ends_on'))
                ->displayFormat('d.m.Y'),
            Toggle::make('is_active')
                ->label(__('recurring_work.fields.is_active'))
                ->helperText(__('recurring_work.help.is_active'))
                ->default(true)
                ->inline(false),
            Textarea::make('description')
                ->label(__('recurring_work.fields.description'))
                ->helperText(__('recurring_work.help.description'))
                ->rows(3)
                ->maxLength(5000)
                ->columnSpan(FieldGrid::LONG),
        ], [
            'main' => [
                'label' => __('recurring_work.sections.main'),
                'icon' => Heroicon::OutlinedArrowPath,
                'fields' => ['title', 'personnel_id', 'kind', 'frequency', 'weekday', 'month_day', 'priority'],
            ],
            'where' => [
                'label' => __('recurring_work.sections.where'),
                'icon' => Heroicon::OutlinedBriefcase,
                'fields' => ['org_unit_id', 'project_id', 'category_code', 'starts_on', 'ends_on', 'is_active', 'description'],
            ],
        ]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('recurring_work.sections.main'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->columns(['default' => 1, 'md' => 3])
                ->components([
                    TextEntry::make('description')->label(__('recurring_work.fields.description'))->placeholder('–')->columnSpanFull(),
                    TextEntry::make('personnel.full_name')->label(__('recurring_work.fields.personnel'))->icon(Heroicon::OutlinedUser),
                    TextEntry::make('kind')->label(__('recurring_work.fields.kind'))->badge(),
                    TextEntry::make('schedule')
                        ->label(__('recurring_work.fields.frequency'))
                        ->state(fn (RecurringWork $record): string => self::scheduleText($record)),
                    TextEntry::make('priority')->label(__('recurring_work.fields.priority'))->badge(),
                    TextEntry::make('orgUnit.name')->label(__('recurring_work.fields.org_unit'))->placeholder('–'),
                    TextEntry::make('project.display_name')->label(__('recurring_work.fields.project'))->placeholder('–'),
                    TextEntry::make('category_code')
                        ->label(__('recurring_work.fields.category'))
                        ->formatStateUsing(fn (?string $state): ?string => app(WorkCategoryCatalog::class)->label($state))
                        ->placeholder('–'),
                    TextEntry::make('starts_on')->label(__('recurring_work.fields.starts_on'))->date('d.m.Y'),
                    TextEntry::make('ends_on')->label(__('recurring_work.fields.ends_on'))->date('d.m.Y')->placeholder('–'),
                    TextEntry::make('is_active')
                        ->label(__('recurring_work.fields.is_active'))
                        ->formatStateUsing(fn (bool $state): string => $state ? __('recurring_work.values.active') : __('recurring_work.values.passive'))
                        ->badge()
                        ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    TextEntry::make('compliance')
                        ->label(__('recurring_work.fields.compliance'))
                        ->state(fn (RecurringWork $record): string => self::complianceText($record))
                        ->color(fn (RecurringWork $record): string => self::complianceColor($record))
                        ->columnSpan(['default' => 1, 'md' => 2]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('recurring_work.fields.title'))
                    ->description(fn (RecurringWork $record): ?string => $record->kind?->getLabel())
                    ->weight('medium')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('personnel.full_name')
                    ->label(__('recurring_work.fields.personnel'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->sortable(),
                TextColumn::make('frequency')
                    ->label(__('recurring_work.fields.frequency'))
                    ->badge()
                    ->description(fn (RecurringWork $record): ?string => self::dayText($record)),
                TextColumn::make('compliance')
                    ->label(__('recurring_work.fields.compliance'))
                    ->state(fn (RecurringWork $record): string => self::complianceText($record))
                    ->color(fn (RecurringWork $record): string => self::complianceColor($record))
                    ->tooltip(__('recurring_work.help.compliance')),
                TextColumn::make('priority')
                    ->label(__('recurring_work.fields.priority'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('project.display_name')
                    ->label(__('recurring_work.fields.project'))
                    ->placeholder('–')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label(__('recurring_work.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('archive')
                    ->label(__('recurring_work.filters.archive'))
                    ->options([
                        'active' => __('recurring_work.filters.archive_active'),
                        'archived' => __('recurring_work.filters.archive_archived'),
                        'all' => __('recurring_work.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(RecurringWorkQueries::class)->applyArchive($query, (string) ($data['value'] ?? 'active'))),
                SelectFilter::make('personnel_id')
                    ->label(__('recurring_work.fields.personnel'))
                    ->relationship('personnel', 'full_name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('frequency')
                    ->label(__('recurring_work.fields.frequency'))
                    ->options(RecurrenceFrequency::class),
                SelectFilter::make('kind')
                    ->label(__('recurring_work.fields.kind'))
                    ->options(RecurringWorkKind::class),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                self::archiveAction(),
            ])
            ->toolbarActions([])
            ->defaultSort('title')
            ->emptyStateIcon(Heroicon::OutlinedArrowPath)
            ->emptyStateHeading(__('recurring_work.empty'))
            ->emptyStateDescription(__('recurring_work.empty_help'));
    }

    /** Arsivle / arsivden cikar (D-156): tek dugme, duruma gore. */
    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->label(fn (RecurringWork $record): string => $record->archived_at === null ? __('recurring_work.actions.archive') : __('recurring_work.actions.unarchive'))
            ->icon(fn (RecurringWork $record): Heroicon => $record->archived_at === null ? Heroicon::OutlinedArchiveBox : Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->visible(fn (RecurringWork $record): bool => auth()->user()?->can('archive', $record) ?? false)
            ->action(function (RecurringWork $record): void {
                try {
                    app(RecurringWorkService::class)->setArchived($record, $record->archived_at === null);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function getRelations(): array
    {
        return [
            OccurrencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringWorks::route('/'),
            'create' => CreateRecurringWork::route('/create'),
            'view' => ViewRecurringWork::route('/{record}'),
            'edit' => EditRecurringWork::route('/{record}/edit'),
        ];
    }

    /** "Her cuma", "Her ayın 5'i", "Hafta içi her gün". */
    public static function dayText(RecurringWork $record): ?string
    {
        return match ($record->frequency) {
            RecurrenceFrequency::Daily => __('recurring_work.values.every_workday'),
            RecurrenceFrequency::Weekly => __('recurring_work.values.every_weekday', ['day' => self::weekdays()[$record->weekday ?? 1] ?? '']),
            RecurrenceFrequency::Monthly => $record->month_day !== null
                ? __('recurring_work.values.every_month_day', ['day' => $record->month_day])
                : __('recurring_work.values.every_month_first'),
            default => null,
        };
    }

    public static function scheduleText(RecurringWork $record): string
    {
        return trim(($record->frequency?->getLabel() ?? '').' · '.(self::dayText($record) ?? ''), ' ·');
    }

    /** Son donemler: ✓ zamaninda, ◔ gec, ✗ yapilmadi, ○ acik (en eski solda) ve "6/8". */
    public static function complianceText(RecurringWork $record): string
    {
        $rows = self::complianceRows($record);

        if ($rows === []) {
            return (string) __('recurring_work.values.no_period');
        }

        $marks = array_map(fn (array $row): string => match ($row['state']) {
            RecurringWorkQueries::ON_TIME => '✓',
            RecurringWorkQueries::LATE => '◔',
            RecurringWorkQueries::MISSED => '✗',
            default => '○',
        }, array_reverse($rows));
        $summary = RecurringWorkQueries::summarize($rows);

        return implode(' ', $marks).($summary['total'] > 0 ? '  '.$summary['done'].'/'.$summary['total'] : '');
    }

    public static function complianceColor(RecurringWork $record): string
    {
        $summary = RecurringWorkQueries::summarize(self::complianceRows($record));

        return match (true) {
            $summary['total'] === 0 => 'gray',
            $summary['missed'] === 0 => 'success',
            $summary['missed'] * 4 <= $summary['total'] => 'warning',
            default => 'danger',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        $days = [];

        foreach (range(1, 7) as $day) {
            $days[$day] = (string) __('recurring_work.weekdays.'.$day);
        }

        return $days;
    }

    /** @return list<array{state: string, period: string, item: int}> */
    private static function complianceRows(RecurringWork $record): array
    {
        // Ayni istekte tablo satirlari tek tek sorgu atmasin: tanim basina bir kez.
        static $cache = [];
        $id = (int) $record->getKey();

        return $cache[$id] ??= app(RecurringWorkQueries::class)->compliance([$id])[$id] ?? [];
    }

    private static function frequency(mixed $value): ?RecurrenceFrequency
    {
        return $value instanceof RecurrenceFrequency ? $value : RecurrenceFrequency::tryFrom((string) $value);
    }
}
