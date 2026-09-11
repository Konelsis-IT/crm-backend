<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstimateVersions\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\PricingScenario;
use App\Services\Acquisition\PricingScenarioService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ScenariosRelationManager extends RelationManager
{
    protected static string $relationship = 'scenarios';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedScale;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pricing_scenario.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('pricing_scenario.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('scenario_code')
                            ->label(__('pricing_scenario.fields.scenario_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('name')
                            ->label(__('pricing_scenario.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('target_margin_pct')
                            ->label(__('pricing_scenario.fields.target_margin_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('adjustment_pct')
                            ->label(__('pricing_scenario.fields.adjustment_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(-100)
                            ->maxValue(100),
                        TextInput::make('total_price')
                            ->label(__('pricing_scenario.fields.total_price'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),
                        Toggle::make('is_selected')
                            ->label(__('pricing_scenario.fields.is_selected')),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('pricing_scenario.label'))
            ->heading(__('pricing_scenario.relation.title'))
            ->recordTitleAttribute('scenario_code')
            ->columns([
                TextColumn::make('scenario_code')
                    ->label(__('pricing_scenario.fields.scenario_code')),
                TextColumn::make('name')
                    ->label(__('pricing_scenario.fields.name')),
                TextColumn::make('target_margin_pct')
                    ->label(__('pricing_scenario.fields.target_margin_pct'))
                    ->suffix('%'),
                TextColumn::make('adjustment_pct')
                    ->label(__('pricing_scenario.fields.adjustment_pct'))
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('total_price')
                    ->label(__('pricing_scenario.fields.total_price'))
                    ->numeric(decimalPlaces: 2),
                IconColumn::make('is_selected')
                    ->label(__('pricing_scenario.fields.is_selected'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['estimate_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(PricingScenarioService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (PricingScenario $record, array $data): Model {
                        try {
                            return app(PricingScenarioService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('select')
                    ->label(__('pricing_scenario.actions.select'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (PricingScenario $record): bool => ! $record->is_selected)
                    ->action(function (PricingScenario $record, array $data): void {
                        try {
                            app(\App\Services\Acquisition\PricingScenarioService::class)->select($record);
                            DomainNotifications::success(__('pricing_scenario.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
                DeleteAction::make()
                    ->using(function (PricingScenario $record): bool {
                        try {
                            return app(PricingScenarioService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('scenario_code')
            ->emptyStateHeading(__('pricing_scenario.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedScale);
    }
}
