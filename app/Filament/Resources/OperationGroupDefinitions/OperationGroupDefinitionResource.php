<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationGroupDefinitions;

use App\Enums\Shared\ActiveStatus;
use App\Exceptions\StaleRecordException;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\OperationGroupDefinitions\Pages\ListOperationGroupDefinitions;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\OperationGroupDefinition;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\OperationGroupDefinitionService;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OperationGroupDefinitionResource extends Resource
{
    protected static ?string $model = OperationGroupDefinition::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 110;

    protected static ?string $recordTitleAttribute = 'name_tr';

    public static function getModelLabel(): string
    {
        return __('operation_group_definition.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('operation_group_definition.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('projects.admin_ui')
            && SchemaReadiness::hasBatch('B17')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('operation_group_definition.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('code')
                            ->label(__('operation_group_definition.fields.code'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(32)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
                        TextInput::make('name_tr')
                            ->label(__('operation_group_definition.fields.name_tr'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('operation_group_definition.fields.name_en'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('default_sort_order')
                            ->label(__('operation_group_definition.fields.default_sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(255)
                            ->default(0)
                            ->required(),
                        Select::make('status')
                            ->label(__('operation_group_definition.fields.status'))
                            ->options(ActiveStatus::class)
                            ->default(ActiveStatus::Active->value)
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
                TextColumn::make('default_sort_order')
                    ->label(__('operation_group_definition.fields.default_sort_order'))
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('operation_group_definition.fields.code'))
                    ->searchable(),
                TextColumn::make('name_tr')
                    ->label(__('operation_group_definition.fields.name_tr'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('operation_group_definition.fields.status'))
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (OperationGroupDefinition $record, array $data): Model {
                        try {
                            return app(OperationGroupDefinitionService::class)->update($record, $data);
                        } catch (StaleRecordException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('default_sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationGroupDefinitions::route('/'),
        ];
    }
}
