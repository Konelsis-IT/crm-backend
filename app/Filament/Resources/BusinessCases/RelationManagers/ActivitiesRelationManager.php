<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\RelationManagers;

use App\Enums\Acquisition\BdActivityType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\BusinessDevelopmentActivity;
use App\Services\Acquisition\BusinessDevelopmentActivityService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCalendarDays;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('bd_activity.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('bd_activity.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('activity_type')
                            ->label(__('bd_activity.fields.activity_type'))
                            ->options(BdActivityType::class)
                            ->default(BdActivityType::Meeting->value)
                            ->required()
                            ->native(false),
                        TextInput::make('subject')
                            ->label(__('bd_activity.fields.subject'))
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('occurred_at')
                            ->label(__('bd_activity.fields.occurred_at'))
                            ->required(),
                        TextInput::make('location')
                            ->label(__('bd_activity.fields.location'))
                            ->maxLength(100),
                        Select::make('organizer_employee_id')
                            ->label(__('bd_activity.fields.organizer'))
                            ->relationship('organizer', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('party_id')
                            ->label(__('bd_activity.fields.party'))
                            ->relationship('party', 'display_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('outcome_summary')
                            ->label(__('bd_activity.fields.outcome_summary'))
                            ->columnSpanFull(),
                        Textarea::make('next_action')
                            ->label(__('bd_activity.fields.next_action'))
                            ->columnSpanFull(),
                        DateTimePicker::make('next_action_due_at')
                            ->label(__('bd_activity.fields.next_action_due_at')),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('bd_activity.label'))
            ->heading(__('bd_activity.relation.title'))
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('bd_activity.fields.occurred_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('activity_type')
                    ->label(__('bd_activity.fields.activity_type'))
                    ->badge(),
                TextColumn::make('subject')
                    ->label(__('bd_activity.fields.subject'))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('organizer.full_name')
                    ->label(__('bd_activity.fields.organizer')),
                TextColumn::make('next_action_due_at')
                    ->label(__('bd_activity.fields.next_action_due_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['business_case_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(BusinessDevelopmentActivityService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (BusinessDevelopmentActivity $record, array $data): Model {
                        try {
                            return app(BusinessDevelopmentActivityService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (BusinessDevelopmentActivity $record): bool {
                        try {
                            return app(BusinessDevelopmentActivityService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('occurred_at', 'desc')
            ->emptyStateHeading(__('bd_activity.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }
}
