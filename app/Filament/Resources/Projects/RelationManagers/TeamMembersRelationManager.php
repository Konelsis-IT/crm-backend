<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\TeamMemberStatus;
use App\Enums\Project\TeamRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectTeamMember;
use App\Models\Project\ProjectWorkstream;
use App\Services\Project\ProjectTeamMemberService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Proje ekibi: gorevlendirme, sorumlu isareti, gorev bitirme. */
class TeamMembersRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'teamMembers';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUserGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_team_member.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_team_member.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('personnel_id')
                        ->label(__('project_team_member.fields.personnel'))
                        ->relationship('personnel', 'full_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false)
                        ->native(false),
                    Select::make('team_role')
                        ->label(__('project_team_member.fields.team_role'))
                        ->options(TeamRole::class)
                        ->default(TeamRole::Engineer->value)
                        ->required()
                        ->native(false),
                    Select::make('workstream_id')
                        ->label(__('project_team_member.fields.workstream'))
                        ->relationship(
                            'workstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->getOptionLabelFromRecordUsing(fn (ProjectWorkstream $record): string => $record->group->localizedName())
                        ->searchable()
                        ->preload()
                        ->native(false),
                    TextInput::make('allocation_pct')
                        ->label(__('project_team_member.fields.allocation_pct'))
                        ->helperText(__('project_team_member.help.allocation_pct'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(100)
                        ->required(),
                    DatePicker::make('assigned_from')
                        ->label(__('project_team_member.fields.assigned_from'))
                        ->displayFormat('d.m.Y')
                        ->default(now()->toDateString())
                        ->required(),
                    DatePicker::make('assigned_until')
                        ->label(__('project_team_member.fields.assigned_until'))
                        ->displayFormat('d.m.Y'),
                    Toggle::make('is_lead')
                        ->label(__('project_team_member.fields.is_lead')),
                    Select::make('status')
                        ->label(__('project_team_member.fields.status'))
                        ->options(TeamMemberStatus::class)
                        ->default(TeamMemberStatus::Active->value)
                        ->required()
                        ->native(false),
                    Textarea::make('note')
                        ->label(__('project_team_member.fields.note'))
                        ->columnSpanFull(),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('project_team_member.label'))
            ->heading(__('project_team_member.relation.title'))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('personnel.full_name')
                    ->label(__('project_team_member.fields.personnel'))
                    ->searchable(),
                TextColumn::make('team_role')
                    ->label(__('project_team_member.fields.team_role'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('workstream.group.name_tr')
                    ->label(__('project_team_member.fields.workstream'))
                    ->placeholder('-'),
                TextColumn::make('allocation_pct')
                    ->label(__('project_team_member.fields.allocation_pct'))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' %'),
                TextColumn::make('assigned_from')
                    ->label(__('project_team_member.fields.assigned_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('assigned_until')
                    ->label(__('project_team_member.fields.assigned_until'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                IconColumn::make('is_lead')
                    ->label(__('project_team_member.fields.is_lead'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('project_team_member.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('project_team_member.fields.status'))
                    ->options(TeamMemberStatus::class),
                SelectFilter::make('team_role')
                    ->label(__('project_team_member.fields.team_role'))
                    ->options(TeamRole::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProjectTeamMemberService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectTeamMember $record, array $data): Model {
                        try {
                            return app(ProjectTeamMemberService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('end')
                    ->label(__('project_team_member.actions.end'))
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ProjectTeamMember $record): bool => $record->status === TeamMemberStatus::Active && Gate::allows('update', $record))
                    ->action(function (ProjectTeamMember $record): void {
                        try {
                            app(ProjectTeamMemberService::class)->end($record);
                            DomainNotifications::success(__('project_team_member.messages.ended'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('assigned_from', 'desc')
            ->emptyStateHeading(__('project_team_member.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
