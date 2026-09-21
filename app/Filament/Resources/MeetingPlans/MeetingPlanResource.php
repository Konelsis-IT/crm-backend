<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans;

use App\Enums\Party\MeetingChannel;
use App\Enums\Party\MeetingPlanSource;
use App\Enums\Party\MeetingPlanStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\MeetingPlans\Pages\CreateMeetingPlan;
use App\Filament\Resources\MeetingPlans\Pages\EditMeetingPlan;
use App\Filament\Resources\MeetingPlans\Pages\ListMeetingPlans;
use App\Filament\Resources\MeetingPlans\Pages\MeetingPlanCalendar;
use App\Filament\Resources\MeetingPlans\Pages\ViewMeetingPlan;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Support\FieldGrid;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\Personnel;
use App\Query\Party\MeetingPlanQueries;
use App\Query\Party\PartyQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Gorusme plani (B34, D-109; 21 Eylul 2026 kullanici istegi): hangi
 * personelin ne zaman hangi tarafla gorusecegi / gorustugu. Ana sayfa ay
 * takvimidir (sosyal medya takviminin ortak bileseni); liste sayfasi
 * suzgecli tablodur. Gorusme notlari buraya kendiliginden yansir; planli
 * gorusme icin 1 gun once ve gunun sabahi zil hatirlatmasi gider.
 */
class MeetingPlanResource extends Resource
{
    protected static ?string $model = MeetingPlan::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'meeting-plans';

    public static function getModelLabel(): string
    {
        return __('meeting_plan.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('meeting_plan.plural');
    }

    /** Str::ucwords Turkce harfleri bozar; baslik bicimi dil dosyasindan. */
    public static function getTitleCasePluralModelLabel(): string
    {
        return __('meeting_plan.plural_title');
    }

    public static function getTitleCaseModelLabel(): string
    {
        return __('meeting_plan.label_title');
    }

    public static function canAccess(): bool
    {
        return SchemaReadiness::hasBatch('B34') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('meeting_plan.sections.main'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('party_id')
                        ->label(__('meeting_plan.fields.party'))
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => app(PartyQueries::class)->searchOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? app(PartyQueries::class)->displayName((int) $value) : null)
                        ->options(fn (): array => app(PartyQueries::class)->searchOptions(''))
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('contact_relationship_id', null))
                        ->native(false)
                        ->columnSpan(FieldGrid::HALF),
                    Select::make('contact_relationship_id')
                        ->label(__('meeting_plan.fields.contact'))
                        ->options(fn (Get $get): array => app(PartyQueries::class)->contactOptions(filled($get('party_id')) ? (int) $get('party_id') : null))
                        ->searchable()
                        ->placeholder('-')
                        ->native(false)
                        ->columnSpan(FieldGrid::HALF),
                    DatePicker::make('planned_on')
                        ->label(__('meeting_plan.fields.planned_on'))
                        ->displayFormat('d.m.Y')
                        ->default(fn (): string => MeetingPlanQueries::today()->toDateString())
                        ->required(),
                    Select::make('channel')
                        ->label(__('meeting_plan.fields.channel'))
                        ->options(MeetingChannel::class)
                        ->default(MeetingChannel::Visit->value)
                        ->required()
                        ->native(false),
                    Select::make('personnel_id')
                        ->label(__('meeting_plan.fields.personnel'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->default(fn () => auth()->id())
                        ->required()
                        ->searchable()
                        ->native(false),
                    Select::make('participant_ids')
                        ->label(__('meeting_plan.fields.participants'))
                        ->helperText(__('meeting_plan.help.participants'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->multiple()
                        ->searchable()
                        ->native(false)
                        ->afterStateHydrated(function (Select $component, ?MeetingPlan $record): void {
                            if ($record !== null) {
                                $component->state($record->participants->pluck('id')->map(fn ($id): int => (int) $id)->all());
                            }
                        })
                        ->columnSpan(FieldGrid::HALF),
                    TextInput::make('subject')
                        ->label(__('meeting_plan.fields.subject'))
                        ->maxLength(200)
                        ->columnSpan(FieldGrid::HALF),
                    Textarea::make('note')
                        ->label(__('meeting_plan.fields.note'))
                        ->helperText(__('meeting_plan.help.note'))
                        ->rows(3)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 2])->components([
                Section::make(__('meeting_plan.sections.main'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columns(['default' => 1, 'md' => 2])
                    ->components([
                        TextEntry::make('planned_on')->label(__('meeting_plan.fields.planned_on'))->date('d.m.Y l'),
                        TextEntry::make('status')
                            ->label(__('meeting_plan.fields.status'))
                            ->state(fn (MeetingPlan $record): string => self::statusLabel($record))
                            ->badge()
                            ->color(fn (MeetingPlan $record): string => $record->isOverdue(MeetingPlanQueries::today()) ? 'danger' : ($record->status?->getColor() ?? 'gray')),
                        TextEntry::make('party.display_name')
                            ->label(__('meeting_plan.fields.party'))
                            ->icon(Heroicon::OutlinedBuildingOffice)
                            ->url(fn (MeetingPlan $record): ?string => $record->party !== null ? PartyResource::getUrl('view', ['record' => $record->party]) : null)
                            ->color('primary'),
                        TextEntry::make('contact_name')
                            ->label(__('meeting_plan.fields.contact'))
                            ->state(fn (MeetingPlan $record): ?string => $record->contact?->displayName())
                            ->placeholder('-'),
                        TextEntry::make('channel')->label(__('meeting_plan.fields.channel'))->badge(),
                        TextEntry::make('source')->label(__('meeting_plan.fields.source'))->badge()->color('gray'),
                        TextEntry::make('subject')->label(__('meeting_plan.fields.subject'))->placeholder('-')->columnSpanFull(),
                        TextEntry::make('note')->label(__('meeting_plan.fields.note'))->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make(__('meeting_plan.sections.people'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->components([
                        TextEntry::make('personnel.full_name')
                            ->label(__('meeting_plan.fields.personnel'))
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->placeholder(__('meeting_plan.values.no_personnel')),
                        TextEntry::make('participants_list')
                            ->label(__('meeting_plan.fields.participants'))
                            ->state(fn (MeetingPlan $record): array => $record->participants->map(fn (Personnel $personnel): string => (string) $personnel->full_name)->all())
                            ->listWithLineBreaks()
                            ->placeholder('-'),
                        TextEntry::make('completed_at')
                            ->label(__('meeting_plan.fields.completed_at'))
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),
                    ]),
            ]),
            // Sonuc: gorusme notu (Taraf > Gorusme notlari) ve varsa sonraki adim.
            Section::make(__('meeting_plan.sections.result'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->description(__('meeting_plan.help.result'))
                ->visible(fn (MeetingPlan $record): bool => $record->meetingNote !== null)
                ->columns(['default' => 1, 'md' => 3])
                ->components([
                    TextEntry::make('meetingNote.noted_on')->label(__('party_meeting_note.fields.noted_on'))->date('d.m.Y'),
                    TextEntry::make('meetingNote.personnel.full_name')->label(__('party_meeting_note.fields.personnel'))->placeholder('-'),
                    TextEntry::make('meetingNote.next_action_on')->label(__('party_meeting_note.fields.next_action_on'))->date('d.m.Y')->placeholder('-'),
                    TextEntry::make('meetingNote.note')->label(__('party_meeting_note.fields.note'))->columnSpanFull(),
                    TextEntry::make('meetingNote.next_action')->label(__('party_meeting_note.fields.next_action'))->placeholder('-')->columnSpanFull(),
                ]),
            Section::make(__('meeting_plan.sections.follow_up'))
                ->icon(Heroicon::OutlinedArrowUturnRight)
                ->visible(fn (MeetingPlan $record): bool => $record->followUpNote !== null)
                ->columns(['default' => 1, 'md' => 3])
                ->components([
                    TextEntry::make('followUpNote.noted_on')->label(__('meeting_plan.fields.follow_up_of'))->date('d.m.Y'),
                    TextEntry::make('followUpNote.personnel.full_name')->label(__('party_meeting_note.fields.personnel'))->placeholder('-'),
                    TextEntry::make('followUpNote.note')->label(__('party_meeting_note.fields.note'))->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('planned_on')
                    ->label(__('meeting_plan.fields.planned_on'))
                    ->date('d.m.Y D')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('meeting_plan.fields.status'))
                    ->state(fn (MeetingPlan $record): string => self::statusLabel($record))
                    ->badge()
                    ->color(fn (MeetingPlan $record): string => $record->isOverdue(MeetingPlanQueries::today()) ? 'danger' : ($record->status?->getColor() ?? 'gray')),
                TextColumn::make('party.display_name')
                    ->label(__('meeting_plan.fields.party'))
                    ->searchable()
                    ->wrap()
                    ->description(fn (MeetingPlan $record): ?string => $record->contact?->displayName()),
                TextColumn::make('personnel.full_name')
                    ->label(__('meeting_plan.fields.personnel'))
                    ->placeholder('-'),
                TextColumn::make('participants_list')
                    ->label(__('meeting_plan.fields.participants'))
                    ->state(fn (MeetingPlan $record): array => $record->participants->map(fn (Personnel $personnel): string => (string) $personnel->full_name)->all())
                    ->listWithLineBreaks()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('channel')
                    ->label(__('meeting_plan.fields.channel'))
                    ->badge(),
                TextColumn::make('subject')
                    ->label(__('meeting_plan.fields.subject'))
                    ->limit(60)
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('source')
                    ->label(__('meeting_plan.fields.source'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['party', 'contact', 'personnel', 'participants']))
            ->filters([
                SelectFilter::make('personnel')
                    ->label(__('meeting_plan.filters.personnel'))
                    ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? app(MeetingPlanQueries::class)->involving($query, (int) $data['value'])
                        : $query),
                SelectFilter::make('channel')
                    ->label(__('meeting_plan.fields.channel'))
                    ->options(MeetingChannel::class),
                SelectFilter::make('source')
                    ->label(__('meeting_plan.fields.source'))
                    ->options(MeetingPlanSource::class),
                Filter::make('dates')
                    ->label(__('meeting_plan.filters.dates'))
                    ->schema([
                        DatePicker::make('from')->label(__('meeting_plan.filters.from'))->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label(__('meeting_plan.filters.until'))->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => app(MeetingPlanQueries::class)->between($query, $data['from'] ?? null, $data['until'] ?? null)),
            ])
            ->recordActions([
                ViewAction::make(),
                MeetingPlanActions::complete(),
                EditAction::make()->visible(fn (MeetingPlan $record): bool => $record->status === MeetingPlanStatus::Planned && (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->toolbarActions([])
            ->defaultSort('planned_on');
    }

    public static function statusLabel(MeetingPlan $record): string
    {
        return $record->isOverdue(MeetingPlanQueries::today())
            ? __('meeting_plan.values.overdue')
            : (string) ($record->status?->getLabel() ?? '-');
    }

    public static function getPages(): array
    {
        return [
            'index' => MeetingPlanCalendar::route('/'),
            'list' => ListMeetingPlans::route('/liste'),
            'create' => CreateMeetingPlan::route('/create'),
            'view' => ViewMeetingPlan::route('/{record}'),
            'edit' => EditMeetingPlan::route('/{record}/edit'),
        ];
    }
}
