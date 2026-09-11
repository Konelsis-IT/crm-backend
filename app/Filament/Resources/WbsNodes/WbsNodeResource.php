<?php

declare(strict_types=1);

namespace App\Filament\Resources\WbsNodes;

use App\Enums\Project\NodeStatus;
use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\WbsNodes\Pages\EditWbsNode;
use App\Filament\Resources\WbsNodes\Pages\ListWbsNodes;
use App\Filament\Resources\WbsNodes\Pages\ViewWbsNode;
use App\Filament\Resources\WbsNodes\RelationManagers\CbsMappingsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\WbsNode;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WbsNodeResource extends Resource
{
    protected static ?string $model = WbsNode::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 13;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'wbs_code';

    public static function getModelLabel(): string
    {
        return __('wbs_node.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('wbs_node.plural');
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
            Section::make(__('wbs_node.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('wbs_code')
                            ->label(__('wbs_node.fields.wbs_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('name')
                            ->label(__('wbs_node.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('parent_id')
                            ->label(__('wbs_node.fields.parent'))
                            ->relationship(
                            'parent',
                            'wbs_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $record?->project_id),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('wbs_node.fields.sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Select::make('status')
                            ->label(__('wbs_node.fields.status'))
                            ->options(NodeStatus::class)
                            ->default(NodeStatus::Active->value)
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
                TextColumn::make('project.name')
                    ->label(__('wbs_node.fields.project'))
                    ->limit(30),
                TextColumn::make('wbs_code')
                    ->label(__('wbs_node.fields.wbs_code')),
                TextColumn::make('name')
                    ->label(__('wbs_node.fields.name')),
                TextColumn::make('level')
                    ->label(__('wbs_node.fields.level')),
                TextColumn::make('status')
                    ->label(__('wbs_node.fields.status'))
                    ->badge(),
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
            CbsMappingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWbsNodes::route('/'),
            'view' => ViewWbsNode::route('/{record}'),
            'edit' => EditWbsNode::route('/{record}/edit'),
        ];
    }
}
