<?php

declare(strict_types=1);

namespace App\Filament\Resources\Positions;

use App\Enums\Personnel\PositionStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Positions\Pages\ListPositions;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Position;
use App\Services\Personnel\PositionService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
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
use UnitEnum;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Administrative;

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('position.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('position.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('personnel.admin_ui')
            && SchemaReadiness::hasBatch('B03')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('position.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('org_unit_id')
                        ->label(__('position.fields.org_unit'))
                        ->relationship('orgUnit', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    TextInput::make('code')
                        ->label(__('position.fields.code'))
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false),
                    TextInput::make('title')
                        ->label(__('position.fields.title'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('grade')
                        ->label(__('position.fields.grade'))
                        ->maxLength(32),
                    TextInput::make('managerial_level')
                        ->label(__('position.fields.managerial_level'))
                        ->helperText(__('position.help.managerial_level'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(5)
                        ->default(0)
                        ->required(),
                    TextInput::make('headcount')
                        ->label(__('position.fields.headcount'))
                        ->numeric()
                        ->minValue(0)
                        ->default(1)
                        ->required(),
                    Select::make('status')
                        ->label(__('position.fields.status'))
                        ->options(PositionStatus::class)
                        ->default(PositionStatus::Active->value)
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
                    ->label(__('position.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('position.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('orgUnit.name')
                    ->label(__('position.fields.org_unit'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('headcount')
                    ->label(__('position.fields.headcount')),
                TextColumn::make('assignee_count')
                    ->label(__('position.fields.assignee_count'))
                    ->counts('assignments'),
                TextColumn::make('status')
                    ->label(__('position.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('org_unit_id')
                    ->label(__('position.fields.org_unit'))
                    ->relationship('orgUnit', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Position $record, array $data): Model {
                        try {
                            return app(PositionService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('title');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPositions::route('/'),
        ];
    }
}
