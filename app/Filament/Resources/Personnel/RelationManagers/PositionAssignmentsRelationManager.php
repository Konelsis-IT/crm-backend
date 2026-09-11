<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Exceptions\DuplicateRecordException;
use App\Exceptions\StaleRecordException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\PositionAssignment;
use App\Services\Personnel\PositionAssignmentService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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
use Illuminate\Validation\ValidationException;

/**
 * Personelin pozisyon atamalari (birden fazla olabilir; asil pozisyon +
 * pay yuzdesi ile gosterilir).
 */
class PositionAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'positionAssignments';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedIdentification;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('position.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('position.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('position_id')
                        ->label(__('position.label'))
                        ->relationship('position', 'title')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Toggle::make('is_primary')
                        ->label(__('position.fields.is_primary'))
                        ->default(true),
                    TextInput::make('allocation_pct')
                        ->label(__('position.fields.allocation_pct'))
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->default(100)
                        ->required(),
                    DatePicker::make('valid_from')
                        ->label(__('position.fields.valid_from'))
                        ->required()
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('valid_until')
                        ->label(__('position.fields.valid_until'))
                        ->displayFormat('d.m.Y'),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('position.label'))
            ->heading(__('position.relation.title'))
            ->recordTitleAttribute('position.title')
            ->columns([
                TextColumn::make('position.title')
                    ->label(__('position.label'))
                    ->searchable(),
                TextColumn::make('position.orgUnit.name')
                    ->label(__('position.fields.org_unit'))
                    ->placeholder('-'),
                IconColumn::make('is_primary')
                    ->label(__('position.fields.is_primary'))
                    ->boolean(),
                TextColumn::make('allocation_pct')
                    ->label(__('position.fields.allocation_pct'))
                    ->suffix('%'),
                TextColumn::make('valid_from')
                    ->label(__('position.fields.valid_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('position.fields.valid_until'))
                    ->date('d.m.Y')
                    ->placeholder(__('assignment.messages.ongoing')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['personnel_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(PositionAssignmentService::class)->create($data);
                        } catch (DuplicateRecordException) {
                            throw ValidationException::withMessages([
                                'data.is_primary' => __('position.validation.duplicate_primary'),
                            ]);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (PositionAssignment $record, array $data): Model {
                        try {
                            return app(PositionAssignmentService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        } catch (DuplicateRecordException) {
                            throw ValidationException::withMessages([
                                'data.is_primary' => __('position.validation.duplicate_primary'),
                            ]);
                        }
                    }),
                DeleteAction::make()
                    ->using(fn (PositionAssignment $record): bool => app(PositionAssignmentService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading(__('position.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedIdentification);
    }
}
