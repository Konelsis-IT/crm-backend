<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\MilestoneKind;
use App\Enums\Project\MilestoneStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\Milestone;
use App\Services\Project\MilestoneService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MilestonesRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'milestones';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedFlag;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('milestone.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('milestone.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('milestone_code')
                            ->label(__('milestone.fields.milestone_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('name')
                            ->label(__('milestone.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('milestone_kind')
                            ->label(__('milestone.fields.milestone_kind'))
                            ->options(MilestoneKind::class)
                            ->default(MilestoneKind::Internal->value)
                            ->required()
                            ->native(false),
                        Select::make('wbs_node_id')
                            ->label(__('milestone.fields.wbs_node'))
                            ->relationship(
                            'wbsNode',
                            'wbs_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('contract_milestone_id')
                            ->label(__('milestone.fields.contract_milestone'))
                            ->relationship('contractMilestone', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('planned_at')
                            ->label(__('milestone.fields.planned_at'))
                            ->displayFormat('d.m.Y')
                            ->required(),
                        DatePicker::make('baseline_at')
                            ->label(__('milestone.fields.baseline_at'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('forecast_at')
                            ->label(__('milestone.fields.forecast_at'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('actual_at')
                            ->label(__('milestone.fields.actual_at'))
                            ->displayFormat('d.m.Y'),
                        Select::make('status')
                            ->label(__('milestone.fields.status'))
                            ->options(MilestoneStatus::class)
                            ->default(MilestoneStatus::Planned->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('milestone.label'))
            ->heading(__('milestone.relation.title'))
            ->recordTitleAttribute('milestone_code')
            ->columns([
                TextColumn::make('milestone_code')
                    ->label(__('milestone.fields.milestone_code')),
                TextColumn::make('name')
                    ->label(__('milestone.fields.name')),
                TextColumn::make('milestone_kind')
                    ->label(__('milestone.fields.milestone_kind'))
                    ->badge(),
                TextColumn::make('planned_at')
                    ->label(__('milestone.fields.planned_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('forecast_at')
                    ->label(__('milestone.fields.forecast_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('actual_at')
                    ->label(__('milestone.fields.actual_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('milestone.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(MilestoneService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Milestone $record, array $data): Model {
                        try {
                            return app(MilestoneService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (Milestone $record): bool {
                        try {
                            return app(MilestoneService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('planned_at')
            ->emptyStateHeading(__('milestone.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedFlag);
    }
}
