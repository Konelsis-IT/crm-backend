<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageNodes;

use App\Filament\Clusters\Settings;
use App\Filament\Resources\StageNodes\Pages\EditStageNode;
use App\Filament\Resources\StageNodes\Pages\ListStageNodes;
use App\Filament\Resources\StageNodes\Pages\ViewStageNode;
use App\Filament\Resources\StageNodes\RelationManagers\DependenciesRelationManager;
use App\Filament\Resources\StageNodes\RelationManagers\RequirementDefinitionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageNode;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StageNodeResource extends Resource
{
    protected static ?string $model = StageNode::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 122;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'stage_code';

    public static function getModelLabel(): string
    {
        return __('stage_node.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stage_node.plural');
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
            Section::make(__('stage_node.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('stage_code')
                            ->label(__('stage_node.fields.stage_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('sequence_no')
                            ->label(__('stage_node.fields.sequence_no'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->default(0)
                            ->required(),
                        TextInput::make('name_tr')
                            ->label(__('stage_node.fields.name_tr'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('stage_node.fields.name_en'))
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_hard_gate')
                            ->label(__('stage_node.fields.is_hard_gate'))
                            ->default(true),
                        Select::make('owner_group_definition_id')
                            ->label(__('stage_node.fields.owner_group_definition'))
                            ->relationship('ownerGroup', 'name_tr')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('stage_node.fields.description'))
                            ->columnSpanFull(),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('templateVersion.template.code')
                    ->label(__('stage_node.fields.template')),
                TextColumn::make('templateVersion.version_no')
                    ->label(__('stage_node.fields.template_version')),
                TextColumn::make('stage_code')
                    ->label(__('stage_node.fields.stage_code')),
                TextColumn::make('name_tr')
                    ->label(__('stage_node.fields.name_tr')),
                IconColumn::make('is_hard_gate')
                    ->label(__('stage_node.fields.is_hard_gate'))
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RequirementDefinitionsRelationManager::class,
            DependenciesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStageNodes::route('/'),
            'view' => ViewStageNode::route('/{record}'),
            'edit' => EditStageNode::route('/{record}/edit'),
        ];
    }
}
