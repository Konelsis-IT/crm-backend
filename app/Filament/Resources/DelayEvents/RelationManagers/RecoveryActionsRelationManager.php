<?php

declare(strict_types=1);

namespace App\Filament\Resources\DelayEvents\RelationManagers;

use App\Enums\Project\RecoveryActionStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\RecoveryAction;
use App\Services\Project\RecoveryActionService;
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

class RecoveryActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'recoveryActions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedWrenchScrewdriver;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('recovery_action.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('recovery_action.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('description')
                            ->label(__('recovery_action.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('owner_personnel_id')
                            ->label(__('recovery_action.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('expected_recovery_days')
                            ->label(__('recovery_action.fields.expected_recovery_days'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(3650),
                        DateTimePicker::make('due_at')
                            ->label(__('recovery_action.fields.due_at'))
                            ->required(),
                        Select::make('status')
                            ->label(__('recovery_action.fields.status'))
                            ->options(RecoveryActionStatus::class)
                            ->default(RecoveryActionStatus::Planned->value)
                            ->required()
                            ->native(false),
                        DateTimePicker::make('completed_at')
                            ->label(__('recovery_action.fields.completed_at')),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('recovery_action.label'))
            ->heading(__('recovery_action.relation.title'))
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')
                    ->label(__('recovery_action.fields.description'))
                    ->limit(50),
                TextColumn::make('owner.full_name')
                    ->label(__('recovery_action.fields.owner')),
                TextColumn::make('expected_recovery_days')
                    ->label(__('recovery_action.fields.expected_recovery_days'))
                    ->placeholder('-'),
                TextColumn::make('due_at')
                    ->label(__('recovery_action.fields.due_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('recovery_action.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['delay_event_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(RecoveryActionService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (RecoveryAction $record, array $data): Model {
                        try {
                            return app(RecoveryActionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (RecoveryAction $record): bool {
                        try {
                            return app(RecoveryActionService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('due_at')
            ->emptyStateHeading(__('recovery_action.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedWrenchScrewdriver);
    }
}
