<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffs;

use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\DepartmentHandoffs\Pages\EditDepartmentHandoff;
use App\Filament\Resources\DepartmentHandoffs\Pages\ListDepartmentHandoffs;
use App\Filament\Resources\DepartmentHandoffs\Pages\ViewDepartmentHandoff;
use App\Filament\Resources\DepartmentHandoffs\RelationManagers\VersionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\DepartmentHandoff;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentHandoffResource extends Resource
{
    protected static ?string $model = DepartmentHandoff::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 16;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('department_handoff.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('department_handoff.plural');
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
            Section::make(__('department_handoff.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        DateTimePicker::make('sla_due_at')
                            ->label(__('department_handoff.fields.sla_due_at')),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label(__('department_handoff.fields.project'))
                    ->limit(30),
                TextColumn::make('sourceWorkstream.group.name_tr')
                    ->label(__('department_handoff.fields.source_workstream')),
                TextColumn::make('targetWorkstream.group.name_tr')
                    ->label(__('department_handoff.fields.target_workstream')),
                TextColumn::make('status')
                    ->label(__('department_handoff.fields.status'))
                    ->badge(),
                TextColumn::make('accepted_at')
                    ->label(__('department_handoff.fields.accepted_at'))
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
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartmentHandoffs::route('/'),
            'view' => ViewDepartmentHandoff::route('/{record}'),
            'edit' => EditDepartmentHandoff::route('/{record}/edit'),
        ];
    }
}
