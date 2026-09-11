<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\NodeStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\WbsNodes\WbsNodeResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\WbsNode;
use App\Services\Project\WbsNodeService;
use BackedEnum;
use Filament\Actions\Action;
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

class WbsNodesRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'wbsNodes';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedRectangleGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('wbs_node.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('wbs_node.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('wbs_code')
                            ->label(__('wbs_node.fields.wbs_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('name')
                            ->label(__('wbs_node.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('parent_id')
                            ->label(__('wbs_node.fields.parent'))
                            ->relationship(
                            'parent',
                            'wbs_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('wbs_node.fields.sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Select::make('status')
                            ->label(__('wbs_node.fields.status'))
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
            ->modelLabel(__('wbs_node.label'))
            ->heading(__('wbs_node.relation.title'))
            ->recordTitleAttribute('wbs_code')
            ->columns([
                TextColumn::make('wbs_code')
                    ->label(__('wbs_node.fields.wbs_code'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('wbs_node.fields.name')),
                TextColumn::make('parent.wbs_code')
                    ->label(__('wbs_node.fields.parent'))
                    ->placeholder('-'),
                TextColumn::make('level')
                    ->label(__('wbs_node.fields.level')),
                TextColumn::make('status')
                    ->label(__('wbs_node.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(WbsNodeService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (WbsNode $record): string => WbsNodeResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (WbsNode $record, array $data): Model {
                        try {
                            return app(WbsNodeService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (WbsNode $record): bool {
                        try {
                            return app(WbsNodeService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('wbs_code')
            ->emptyStateHeading(__('wbs_node.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedRectangleGroup);
    }
}
