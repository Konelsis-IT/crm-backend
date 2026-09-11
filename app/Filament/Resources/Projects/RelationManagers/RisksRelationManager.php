<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\ResponseStrategy;
use App\Enums\Project\RiskCategory;
use App\Enums\Project\RiskStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectRisk;
use App\Services\Project\ProjectRiskService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RisksRelationManager extends RelationManager
{
    protected static string $relationship = 'risks';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedShieldExclamation;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_risk.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_risk.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('title')
                            ->label(__('project_risk.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('project_risk.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('category')
                            ->label(__('project_risk.fields.category'))
                            ->options(RiskCategory::class)
                            ->default(RiskCategory::Technical->value)
                            ->required()
                            ->native(false),
                        Select::make('workstream_id')
                            ->label(__('project_risk.fields.workstream'))
                            ->relationship(
                            'workstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectWorkstream $record): string => $record->group->name_tr)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('probability')
                            ->label(__('project_risk.fields.probability'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('impact')
                            ->label(__('project_risk.fields.impact'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->default(3)
                            ->required(),
                        Select::make('response_strategy')
                            ->label(__('project_risk.fields.response_strategy'))
                            ->options(ResponseStrategy::class)
                            ->default(ResponseStrategy::Mitigate->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('project_risk.fields.status'))
                            ->options(RiskStatus::class)
                            ->default(RiskStatus::Identified->value)
                            ->required()
                            ->native(false),
                        Select::make('owner_personnel_id')
                            ->label(__('project_risk.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('review_due_on')
                            ->label(__('project_risk.fields.review_due_on'))
                            ->displayFormat('d.m.Y'),
                        Textarea::make('mitigation_plan')
                            ->label(__('project_risk.fields.mitigation_plan'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('project_risk.label'))
            ->heading(__('project_risk.relation.title'))
            ->recordTitleAttribute('risk_no')
            ->columns([
                TextColumn::make('risk_no')
                    ->label(__('project_risk.fields.risk_no')),
                TextColumn::make('title')
                    ->label(__('project_risk.fields.title'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('project_risk.fields.category'))
                    ->badge(),
                TextColumn::make('probability')
                    ->label(__('project_risk.fields.probability')),
                TextColumn::make('impact')
                    ->label(__('project_risk.fields.impact')),
                TextColumn::make('score')
                    ->label(__('project_risk.fields.score'))
                    ->sortable(),
                TextColumn::make('response_strategy')
                    ->label(__('project_risk.fields.response_strategy'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('project_risk.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProjectRiskService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectRisk $record, array $data): Model {
                        try {
                            return app(ProjectRiskService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('score', 'desc')
            ->emptyStateHeading(__('project_risk.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShieldExclamation);
    }
}
