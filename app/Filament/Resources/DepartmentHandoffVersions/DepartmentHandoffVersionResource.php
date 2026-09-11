<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffVersions;

use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\DepartmentHandoffVersions\Pages\EditDepartmentHandoffVersion;
use App\Filament\Resources\DepartmentHandoffVersions\Pages\ListDepartmentHandoffVersions;
use App\Filament\Resources\DepartmentHandoffVersions\Pages\ViewDepartmentHandoffVersion;
use App\Filament\Resources\DepartmentHandoffVersions\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\DepartmentHandoffVersions\RelationManagers\ReviewsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\DepartmentHandoffVersion;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentHandoffVersionResource extends Resource
{
    protected static ?string $model = DepartmentHandoffVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 17;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('department_handoff_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('department_handoff_version.plural');
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
            Section::make(__('department_handoff_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('snapshot_hash')
                            ->label(__('department_handoff_version.fields.snapshot_hash'))
                            ->maxLength(64)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('handoff.project.name')
                    ->label(__('department_handoff_version.fields.project'))
                    ->limit(30),
                TextColumn::make('version_no')
                    ->label(__('department_handoff_version.fields.version_no')),
                TextColumn::make('status')
                    ->label(__('department_handoff_version.fields.status'))
                    ->badge(),
                TextColumn::make('submitter.full_name')
                    ->label(__('department_handoff_version.fields.submitter'))
                    ->placeholder('-'),
                TextColumn::make('submitted_at')
                    ->label(__('department_handoff_version.fields.submitted_at'))
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
            ItemsRelationManager::class,
            ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartmentHandoffVersions::route('/'),
            'view' => ViewDepartmentHandoffVersion::route('/{record}'),
            'edit' => EditDepartmentHandoffVersion::route('/{record}/edit'),
        ];
    }
}
