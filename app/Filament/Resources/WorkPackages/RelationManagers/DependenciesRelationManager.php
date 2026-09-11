<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkPackages\RelationManagers;

use App\Enums\Project\DependencyType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\WorkPackageDependency;
use App\Services\Project\WorkPackageDependencyService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DependenciesRelationManager extends RelationManager
{
    protected static string $relationship = 'predecessorDependencies';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArrowsRightLeft;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_package_dependency.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('work_package_dependency.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('predecessor_package_id')
                            ->label(__('work_package_dependency.fields.predecessor_package'))
                            ->relationship(
                            'predecessor',
                            'package_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->project_id),
                        )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('dependency_type')
                            ->label(__('work_package_dependency.fields.dependency_type'))
                            ->options(DependencyType::class)
                            ->default(DependencyType::FS->value)
                            ->required()
                            ->native(false),
                        Toggle::make('is_hard')
                            ->label(__('work_package_dependency.fields.is_hard'))
                            ->default(true),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('work_package_dependency.label'))
            ->heading(__('work_package_dependency.relation.title'))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('predecessor.package_code')
                    ->label(__('work_package_dependency.fields.predecessor')),
                TextColumn::make('predecessor.name')
                    ->label(__('work_package_dependency.fields.name')),
                TextColumn::make('dependency_type')
                    ->label(__('work_package_dependency.fields.dependency_type'))
                    ->badge(),
                IconColumn::make('is_hard')
                    ->label(__('work_package_dependency.fields.is_hard'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['successor_package_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(WorkPackageDependencyService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (WorkPackageDependency $record, array $data): Model {
                        try {
                            return app(WorkPackageDependencyService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (WorkPackageDependency $record): bool {
                        try {
                            return app(WorkPackageDependencyService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('work_package_dependency.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft);
    }
}
