<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Acquisition\CostCategory;
use App\Enums\Project\NodeStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\CbsNode;
use App\Services\Project\CbsNodeService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CbsNodesRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'cbsNodes';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedBanknotes;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('cbs_node.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('cbs_node.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('cost_code')
                            ->label(__('cbs_node.fields.cost_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('name')
                            ->label(__('cbs_node.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('cost_category')
                            ->label(__('cbs_node.fields.cost_category'))
                            ->options(CostCategory::class)
                            ->default(CostCategory::Material->value)
                            ->required()
                            ->native(false),
                        Select::make('parent_id')
                            ->label(__('cbs_node.fields.parent'))
                            ->relationship(
                            'parent',
                            'cost_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('status')
                            ->label(__('cbs_node.fields.status'))
                            ->options(NodeStatus::class)
                            ->default(NodeStatus::Active->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('cbs_node.label'))
            ->heading(__('cbs_node.relation.title'))
            ->recordTitleAttribute('cost_code')
            ->columns([
                TextColumn::make('cost_code')
                    ->label(__('cbs_node.fields.cost_code'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('cbs_node.fields.name')),
                TextColumn::make('cost_category')
                    ->label(__('cbs_node.fields.cost_category'))
                    ->badge(),
                TextColumn::make('parent.cost_code')
                    ->label(__('cbs_node.fields.parent'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('cbs_node.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(CbsNodeService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (CbsNode $record, array $data): Model {
                        try {
                            return app(CbsNodeService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (CbsNode $record): bool {
                        try {
                            return app(CbsNodeService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('cost_code')
            ->emptyStateHeading(__('cbs_node.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
