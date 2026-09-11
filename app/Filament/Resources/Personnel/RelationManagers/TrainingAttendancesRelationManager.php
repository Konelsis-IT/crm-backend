<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Enums\Personnel\TrainingAttendanceOutcome;
use App\Exceptions\DuplicateRecordException;
use App\Exceptions\StaleRecordException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\TrainingAttendance;
use App\Services\Personnel\TrainingAttendanceService;
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
use Illuminate\Validation\ValidationException;

/**
 * Personelin egitim katilim kayitlari.
 */
class TrainingAttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'trainingAttendances';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedBookOpen;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('training.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('training.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('training_id')
                        ->label(__('training.label'))
                        ->relationship('training', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    DatePicker::make('attended_on')
                        ->label(__('training.fields.attended_on'))
                        ->displayFormat('d.m.Y'),
                    Select::make('outcome')
                        ->label(__('training.fields.outcome'))
                        ->options(TrainingAttendanceOutcome::class)
                        ->default(TrainingAttendanceOutcome::Registered->value)
                        ->required()
                        ->native(false),
                    TextInput::make('score')
                        ->label(__('training.fields.score'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.5),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('training.label'))
            ->heading(__('training.relation.title'))
            ->recordTitleAttribute('training.name')
            ->columns([
                TextColumn::make('training.name')
                    ->label(__('training.label'))
                    ->searchable(),
                TextColumn::make('training.training_kind')
                    ->label(__('training.fields.training_kind'))
                    ->badge(),
                TextColumn::make('attended_on')
                    ->label(__('training.fields.attended_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('outcome')
                    ->label(__('training.fields.outcome'))
                    ->badge(),
                TextColumn::make('score')
                    ->label(__('training.fields.score'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['personnel_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(TrainingAttendanceService::class)->create($data);
                        } catch (DuplicateRecordException) {
                            throw ValidationException::withMessages([
                                'data.training_id' => __('training.validation.duplicate'),
                            ]);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (TrainingAttendance $record, array $data): Model {
                        try {
                            return app(TrainingAttendanceService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        } catch (DuplicateRecordException) {
                            throw ValidationException::withMessages([
                                'data.training_id' => __('training.validation.duplicate'),
                            ]);
                        }
                    }),
                DeleteAction::make()
                    ->using(fn (TrainingAttendance $record): bool => app(TrainingAttendanceService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('attended_on', 'desc')
            ->emptyStateHeading(__('training.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBookOpen);
    }
}
