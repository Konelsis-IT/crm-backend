<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectWorkstreams;

use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\ProjectWorkstreams\Pages\EditProjectWorkstream;
use App\Filament\Resources\ProjectWorkstreams\Pages\ListProjectWorkstreams;
use App\Filament\Resources\ProjectWorkstreams\Pages\ViewProjectWorkstream;
use App\Filament\Resources\ProjectWorkstreams\RelationManagers\DependenciesRelationManager;
use App\Filament\Resources\ProjectWorkstreams\RelationManagers\WorkPackagesRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectWorkstream;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectWorkstreamResource extends Resource
{
    protected static ?string $model = ProjectWorkstream::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 11;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('project_workstream.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project_workstream.plural');
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
            Section::make(__('project_workstream.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('owner_personnel_id')
                            ->label(__('project_workstream.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('progress_pct')
                            ->label(__('project_workstream.fields.progress_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        DatePicker::make('planned_start_on')
                            ->label(__('project_workstream.fields.planned_start_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('planned_finish_on')
                            ->label(__('project_workstream.fields.planned_finish_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('actual_start_on')
                            ->label(__('project_workstream.fields.actual_start_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('actual_finish_on')
                            ->label(__('project_workstream.fields.actual_finish_on'))
                            ->displayFormat('d.m.Y'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label(__('project_workstream.fields.project'))
                    ->limit(30),
                TextColumn::make('group.name_tr')
                    ->label(__('project_workstream.fields.group')),
                TextColumn::make('owner.full_name')
                    ->label(__('project_workstream.fields.owner')),
                TextColumn::make('status')
                    ->label(__('project_workstream.fields.status'))
                    ->badge(),
                TextColumn::make('progress_pct')
                    ->label(__('project_workstream.fields.progress_pct'))
                    ->suffix('%'),
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
            DependenciesRelationManager::class,
            WorkPackagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectWorkstreams::route('/'),
            'view' => ViewProjectWorkstream::route('/{record}'),
            'edit' => EditProjectWorkstream::route('/{record}/edit'),
        ];
    }
}
