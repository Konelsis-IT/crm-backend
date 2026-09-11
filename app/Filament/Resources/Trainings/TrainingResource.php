<?php

declare(strict_types=1);

namespace App\Filament\Resources\Trainings;

use App\Enums\Personnel\TrainingKind;
use App\Enums\Personnel\TrainingStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\Trainings\Pages\ListTrainings;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Training;
use App\Services\Personnel\TrainingService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TrainingResource extends Resource
{
    protected static ?string $model = Training::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('training.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('training.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('personnel.admin_ui')
            && SchemaReadiness::hasBatch('B13')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('training.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('code')
                        ->label(__('training.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('name')
                        ->label(__('training.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('training_kind')
                        ->label(__('training.fields.training_kind'))
                        ->options(TrainingKind::class)
                        ->required()
                        ->native(false),
                    TextInput::make('provider')
                        ->label(__('training.fields.provider'))
                        ->maxLength(255),
                    DatePicker::make('planned_on')
                        ->label(__('training.fields.planned_on'))
                        ->displayFormat('d.m.Y'),
                    TextInput::make('duration_hours')
                        ->label(__('training.fields.duration_hours'))
                        ->numeric()
                        ->minValue(0)
                        ->step(0.5),
                    Select::make('status')
                        ->label(__('training.fields.status'))
                        ->options(TrainingStatus::class)
                        ->default(TrainingStatus::Planned->value)
                        ->required()
                        ->native(false),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('training.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('training.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('training_kind')
                    ->label(__('training.fields.training_kind'))
                    ->badge(),
                TextColumn::make('provider')
                    ->label(__('training.fields.provider'))
                    ->placeholder('-'),
                TextColumn::make('planned_on')
                    ->label(__('training.fields.planned_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('attendee_count')
                    ->label(__('training.fields.attendee_count'))
                    ->counts('attendances'),
                TextColumn::make('status')
                    ->label(__('training.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('training_kind')
                    ->label(__('training.fields.training_kind'))
                    ->options(TrainingKind::class),
                SelectFilter::make('status')
                    ->label(__('training.fields.status'))
                    ->options(TrainingStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Training $record, array $data): Model {
                        try {
                            return app(TrainingService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainings::route('/'),
        ];
    }
}
