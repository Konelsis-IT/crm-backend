<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\RelationManagers;

use App\Enums\Acquisition\OpportunityStage;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\Opportunity;
use App\Services\Acquisition\OpportunityService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Illuminate\Support\Facades\Gate;

class OpportunityRelationManager extends RelationManager
{
    protected static string $relationship = 'opportunities';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedLightBulb;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('opportunity.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('opportunity.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('probability_pct')
                            ->label(__('opportunity.fields.probability_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        TextInput::make('expected_value')
                            ->label(__('opportunity.fields.expected_value'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        DatePicker::make('expected_decision_on')
                            ->label(__('opportunity.fields.expected_decision_on'))
                            ->displayFormat('d.m.Y'),
                        TextInput::make('market_code')
                            ->label(__('opportunity.fields.market_code'))
                            ->maxLength(32),
                        Textarea::make('competitor_note')
                            ->label(__('opportunity.fields.competitor_note'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('opportunity.relation.title'))
            ->recordTitleAttribute('stage')
            ->columns([
                TextColumn::make('stage')
                    ->label(__('opportunity.fields.stage'))
                    ->badge(),
                TextColumn::make('bid_decision')
                    ->label(__('opportunity.fields.bid_decision'))
                    ->badge(),
                TextColumn::make('probability_pct')
                    ->label(__('opportunity.fields.probability_pct'))
                    ->suffix('%'),
                TextColumn::make('expected_value')
                    ->label(__('opportunity.fields.expected_value'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('expected_decision_on')
                    ->label(__('opportunity.fields.expected_decision_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('bidDecider.full_name')
                    ->label(__('opportunity.fields.bid_decider'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Opportunity $record, array $data): Model {
                        try {
                            return app(OpportunityService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('opportunity.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('opportunity.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedLightBulb);
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (OpportunityStage::cases() as $target) {
            $actions[] = Action::make('status_'.$target->value)
                ->label(__('opportunity.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('opportunity.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (Opportunity $record): bool => Gate::allows('update', $record)
                    && $record->stage->canTransitionTo($target))
                ->action(function (Opportunity $record, array $data) use ($target): void {
                    try {
                        app(OpportunityService::class)->changeStage($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('opportunity.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
