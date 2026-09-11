<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ContractMilestone;
use App\Services\Acquisition\ContractMilestoneService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedFlag;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contract_milestone.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contract_milestone.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('milestone_code')
                            ->label(__('contract_milestone.fields.milestone_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('name')
                            ->label(__('contract_milestone.fields.name'))
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('planned_on')
                            ->label(__('contract_milestone.fields.planned_on'))
                            ->required()
                            ->displayFormat('d.m.Y'),
                        TextInput::make('payment_pct')
                            ->label(__('contract_milestone.fields.payment_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('payment_amount')
                            ->label(__('contract_milestone.fields.payment_amount'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        Textarea::make('description')
                            ->label(__('contract_milestone.fields.description'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contract_milestone.label'))
            ->heading(__('contract_milestone.relation.title'))
            ->recordTitleAttribute('milestone_code')
            ->columns([
                TextColumn::make('milestone_code')
                    ->label(__('contract_milestone.fields.milestone_code')),
                TextColumn::make('name')
                    ->label(__('contract_milestone.fields.name')),
                TextColumn::make('planned_on')
                    ->label(__('contract_milestone.fields.planned_on'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('payment_pct')
                    ->label(__('contract_milestone.fields.payment_pct'))
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('payment_amount')
                    ->label(__('contract_milestone.fields.payment_amount'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['contract_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ContractMilestoneService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ContractMilestone $record, array $data): Model {
                        try {
                            return app(ContractMilestoneService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ContractMilestone $record): bool {
                        try {
                            return app(ContractMilestoneService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('planned_on')
            ->emptyStateHeading(__('contract_milestone.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedFlag);
    }
}
