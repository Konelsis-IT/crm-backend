<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\ComponentScopeState;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectComponent;
use App\Query\Project\ProjectCatalogQueries;
use App\Services\Project\ProjectComponentService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class ComponentsRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'components';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCube;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_component.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_component.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('component_definition_id')
                            ->label(__('project_component.fields.component_definition'))
                            ->relationship(
                                'definition',
                                'name_tr',
                                // Projede zaten olan tanimlar listelenmez (duzenlemede kaydin kendisi kalir).
                                modifyQueryUsing: fn (Builder $query, ?ProjectComponent $record): Builder => $query->whereNotIn(
                                    'id',
                                    app(ProjectCatalogQueries::class)->usedComponentDefinitionIds((int) $this->getOwnerRecord()->getKey(), $record?->getKey() !== null ? (int) $record->getKey() : null),
                                ),
                            )
                            ->unique(
                                table: 'project_components',
                                column: 'component_definition_id',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('project_id', $this->getOwnerRecord()->getKey()),
                            )
                            ->validationMessages(['unique' => __('project_component.validation.duplicate')])
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('scope_state')
                            ->label(__('project_component.fields.scope_state'))
                            ->options(ComponentScopeState::class)
                            ->default(ComponentScopeState::InScope->value)
                            ->required()
                            ->native(false),
                        TextInput::make('capacity_value')
                            ->label(__('project_component.fields.capacity_value'))
                            ->numeric()
                            ->step('0.000001')
                            ->minValue(0),
                        Select::make('capacity_uom_id')
                            ->label(__('project_component.fields.capacity_uom'))
                            ->relationship('capacityUom', 'name_tr')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('note')
                            ->label(__('project_component.fields.note'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('project_component.label'))
            ->heading(__('project_component.relation.title'))
            ->recordTitleAttribute('scope_state')
            ->columns([
                TextColumn::make('definition.code')
                    ->label(__('project_component.fields.code')),
                TextColumn::make('definition.name_tr')
                    ->label(__('project_component.fields.definition')),
                TextColumn::make('scope_state')
                    ->label(__('project_component.fields.scope_state'))
                    ->badge(),
                TextColumn::make('capacity_value')
                    ->label(__('project_component.fields.capacity_value'))
                    ->placeholder('-'),
                TextColumn::make('capacityUom.symbol')
                    ->label(__('project_component.fields.capacity_uom'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProjectComponentService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectComponent $record, array $data): Model {
                        try {
                            return app(ProjectComponentService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ProjectComponent $record): bool {
                        try {
                            return app(ProjectComponentService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('project_component.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCube);
    }
}
