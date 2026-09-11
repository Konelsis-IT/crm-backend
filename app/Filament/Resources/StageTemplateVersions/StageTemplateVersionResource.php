<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplateVersions;

use App\Filament\Clusters\Settings;
use App\Filament\Resources\StageTemplateVersions\Pages\EditStageTemplateVersion;
use App\Filament\Resources\StageTemplateVersions\Pages\ListStageTemplateVersions;
use App\Filament\Resources\StageTemplateVersions\Pages\ViewStageTemplateVersion;
use App\Filament\Resources\StageTemplateVersions\RelationManagers\NodesRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageTemplateVersion;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StageTemplateVersionResource extends Resource
{
    protected static ?string $model = StageTemplateVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 121;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('stage_template_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stage_template_version.plural');
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
            Section::make(__('stage_template_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('change_summary')
                            ->label(__('stage_template_version.fields.change_summary'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.code')
                    ->label(__('stage_template_version.fields.template')),
                TextColumn::make('version_no')
                    ->label(__('stage_template_version.fields.version_no')),
                TextColumn::make('status')
                    ->label(__('stage_template_version.fields.status'))
                    ->badge(),
                TextColumn::make('publisher.full_name')
                    ->label(__('stage_template_version.fields.publisher'))
                    ->placeholder('-'),
                TextColumn::make('published_at')
                    ->label(__('stage_template_version.fields.published_at'))
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
            NodesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStageTemplateVersions::route('/'),
            'view' => ViewStageTemplateVersion::route('/{record}'),
            'edit' => EditStageTemplateVersion::route('/{record}/edit'),
        ];
    }
}
