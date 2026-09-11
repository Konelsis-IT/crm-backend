<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectStageInstances;

use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\ProjectStageInstances\Pages\EditProjectStageInstance;
use App\Filament\Resources\ProjectStageInstances\Pages\ListProjectStageInstances;
use App\Filament\Resources\ProjectStageInstances\Pages\ViewProjectStageInstance;
use App\Filament\Resources\ProjectStageInstances\RelationManagers\EvidenceRelationManager;
use App\Filament\Resources\ProjectStageInstances\RelationManagers\RequirementsRelationManager;
use App\Filament\Resources\ProjectStageInstances\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\ProjectStageInstances\RelationManagers\WaiversRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectStageInstance;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectStageInstanceResource extends Resource
{
    protected static ?string $model = ProjectStageInstance::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 15;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('project_stage_instance.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project_stage_instance.plural');
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
            Section::make(__('project_stage_instance.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('owner_personnel_id')
                            ->label(__('project_stage_instance.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        DatePicker::make('condition_due_on')
                            ->label(__('project_stage_instance.fields.condition_due_on'))
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
                    ->label(__('project_stage_instance.fields.project'))
                    ->limit(30),
                TextColumn::make('stageNode.stage_code')
                    ->label(__('project_stage_instance.fields.stage_code')),
                TextColumn::make('stageNode.name_tr')
                    ->label(__('project_stage_instance.fields.name')),
                TextColumn::make('owner.full_name')
                    ->label(__('project_stage_instance.fields.owner')),
                TextColumn::make('status')
                    ->label(__('project_stage_instance.fields.status'))
                    ->badge(),
                TextColumn::make('passed_at')
                    ->label(__('project_stage_instance.fields.passed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
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
            RequirementsRelationManager::class,
            EvidenceRelationManager::class,
            ReviewsRelationManager::class,
            WaiversRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectStageInstances::route('/'),
            'view' => ViewProjectStageInstance::route('/{record}'),
            'edit' => EditProjectStageInstance::route('/{record}/edit'),
        ];
    }
}
