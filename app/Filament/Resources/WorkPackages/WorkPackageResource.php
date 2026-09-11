<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkPackages;

use App\Enums\Project\WorkPackageStatus;
use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\WorkPackages\Pages\EditWorkPackage;
use App\Filament\Resources\WorkPackages\Pages\ListWorkPackages;
use App\Filament\Resources\WorkPackages\Pages\ViewWorkPackage;
use App\Filament\Resources\WorkPackages\RelationManagers\DependenciesRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\WorkPackage;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkPackageResource extends Resource
{
    protected static ?string $model = WorkPackage::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 12;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'package_code';

    public static function getModelLabel(): string
    {
        return __('work_package.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('work_package.plural');
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
            Section::make(__('work_package.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('name')
                            ->label(__('work_package.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('wbs_node_id')
                            ->label(__('work_package.fields.wbs_node'))
                            ->relationship(
                            'wbsNode',
                            'wbs_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $record?->project_id),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('owner_personnel_id')
                            ->label(__('work_package.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('status')
                            ->label(__('work_package.fields.status'))
                            ->options(WorkPackageStatus::class)
                            ->default(WorkPackageStatus::Planned->value)
                            ->required()
                            ->native(false),
                        DatePicker::make('planned_start_on')
                            ->label(__('work_package.fields.planned_start_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('planned_finish_on')
                            ->label(__('work_package.fields.planned_finish_on'))
                            ->displayFormat('d.m.Y'),
                        Textarea::make('description')
                            ->label(__('work_package.fields.description'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label(__('work_package.fields.project'))
                    ->limit(30),
                TextColumn::make('package_code')
                    ->label(__('work_package.fields.package_code')),
                TextColumn::make('name')
                    ->label(__('work_package.fields.name'))
                    ->limit(40),
                TextColumn::make('workstream.group.name_tr')
                    ->label(__('work_package.fields.workstream')),
                TextColumn::make('status')
                    ->label(__('work_package.fields.status'))
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
            DependenciesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkPackages::route('/'),
            'view' => ViewWorkPackage::route('/{record}'),
            'edit' => EditWorkPackage::route('/{record}/edit'),
        ];
    }
}
