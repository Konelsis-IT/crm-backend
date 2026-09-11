<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplates;

use App\Enums\Project\StageTemplateProjectType;
use App\Enums\Project\StageTemplateStatus;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\StageTemplates\Pages\CreateStageTemplate;
use App\Filament\Resources\StageTemplates\Pages\EditStageTemplate;
use App\Filament\Resources\StageTemplates\Pages\ListStageTemplates;
use App\Filament\Resources\StageTemplates\Pages\ViewStageTemplate;
use App\Filament\Resources\StageTemplates\RelationManagers\VersionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageTemplate;
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

class StageTemplateResource extends Resource
{
    protected static ?string $model = StageTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 120;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('stage_template.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stage_template.plural');
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
            Section::make(__('stage_template.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('code')
                            ->label(__('stage_template.fields.code'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(64)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
                        TextInput::make('name_tr')
                            ->label(__('stage_template.fields.name_tr'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('stage_template.fields.name_en'))
                            ->required()
                            ->maxLength(255),
                        Select::make('project_type')
                            ->label(__('stage_template.fields.project_type'))
                            ->options(StageTemplateProjectType::class)
                            ->default(StageTemplateProjectType::Generic->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('stage_template.fields.status'))
                            ->options(StageTemplateStatus::class)
                            ->default(StageTemplateStatus::Draft->value)
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
                    ->label(__('stage_template.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_tr')
                    ->label(__('stage_template.fields.name_tr'))
                    ->searchable(),
                TextColumn::make('project_type')
                    ->label(__('stage_template.fields.project_type'))
                    ->badge(),
                TextColumn::make('currentVersion.version_no')
                    ->label(__('stage_template.fields.current_version'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('stage_template.fields.status'))
                    ->badge(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStageTemplates::route('/'),
            'create' => CreateStageTemplate::route('/create'),
            'view' => ViewStageTemplate::route('/{record}'),
            'edit' => EditStageTemplate::route('/{record}/edit'),
        ];
    }
}
