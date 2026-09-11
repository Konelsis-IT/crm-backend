<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplateVersions\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Resources\StageNodes\StageNodeResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageNode;
use App\Services\Project\StageNodeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class NodesRelationManager extends RelationManager
{
    protected static string $relationship = 'nodes';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedFlag;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stage_node.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stage_node.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('stage_code')
                            ->label(__('stage_node.fields.stage_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('sequence_no')
                            ->label(__('stage_node.fields.sequence_no'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->default(0)
                            ->required(),
                        TextInput::make('name_tr')
                            ->label(__('stage_node.fields.name_tr'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('stage_node.fields.name_en'))
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_hard_gate')
                            ->label(__('stage_node.fields.is_hard_gate'))
                            ->default(true),
                        Select::make('owner_group_definition_id')
                            ->label(__('stage_node.fields.owner_group_definition'))
                            ->relationship('ownerGroup', 'name_tr')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('stage_node.fields.description'))
                            ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('stage_node.label'))
            ->heading(__('stage_node.relation.title'))
            ->recordTitleAttribute('stage_code')
            ->columns([
                TextColumn::make('sequence_no')
                    ->label(__('stage_node.fields.sequence_no'))
                    ->sortable(),
                TextColumn::make('stage_code')
                    ->label(__('stage_node.fields.stage_code')),
                TextColumn::make('name_tr')
                    ->label(__('stage_node.fields.name_tr')),
                IconColumn::make('is_hard_gate')
                    ->label(__('stage_node.fields.is_hard_gate'))
                    ->boolean(),
                TextColumn::make('ownerGroup.name_tr')
                    ->label(__('stage_node.fields.owner_group'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['stage_template_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(StageNodeService::class)->create($data);
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
                    ->url(fn (StageNode $record): string => StageNodeResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (StageNode $record, array $data): Model {
                        try {
                            return app(StageNodeService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (StageNode $record): bool {
                        try {
                            return app(StageNodeService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sequence_no')
            ->emptyStateHeading(__('stage_node.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedFlag);
    }
}
