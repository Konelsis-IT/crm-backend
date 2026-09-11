<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageNodes\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageDependency;
use App\Services\Project\StageDependencyService;
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
    protected static string $relationship = 'successorDependencies';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArrowsRightLeft;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stage_dependency.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stage_dependency.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('successor_node_id')
                            ->label(__('stage_dependency.fields.successor_node'))
                            ->relationship(
                            'successor',
                            'stage_code',
                            modifyQueryUsing: fn ($query) => $query->where('stage_template_version_id', $this->getOwnerRecord()->stage_template_version_id),
                        )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Toggle::make('is_hard')
                            ->label(__('stage_dependency.fields.is_hard'))
                            ->default(true),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('stage_dependency.label'))
            ->heading(__('stage_dependency.relation.title'))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('successor.stage_code')
                    ->label(__('stage_dependency.fields.successor')),
                TextColumn::make('successor.name_tr')
                    ->label(__('stage_dependency.fields.name')),
                IconColumn::make('is_hard')
                    ->label(__('stage_dependency.fields.is_hard'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['predecessor_node_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(StageDependencyService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (StageDependency $record, array $data): Model {
                        try {
                            return app(StageDependencyService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (StageDependency $record): bool {
                        try {
                            return app(StageDependencyService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('stage_dependency.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft);
    }
}
