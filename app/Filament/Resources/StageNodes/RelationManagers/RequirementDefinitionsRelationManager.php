<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageNodes\RelationManagers;

use App\Enums\Project\EvidenceType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageRequirementDefinition;
use App\Services\Project\StageRequirementDefinitionService;
use BackedEnum;
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

class RequirementDefinitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'requirementDefinitions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedListBullet;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stage_requirement_definition.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stage_requirement_definition.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('requirement_code')
                            ->label(__('stage_requirement_definition.fields.requirement_code'))
                            ->required()
                            ->maxLength(32),
                        Select::make('evidence_type')
                            ->label(__('stage_requirement_definition.fields.evidence_type'))
                            ->options(EvidenceType::class)
                            ->default(EvidenceType::Document->value)
                            ->required()
                            ->native(false),
                        TextInput::make('name_tr')
                            ->label(__('stage_requirement_definition.fields.name_tr'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label(__('stage_requirement_definition.fields.name_en'))
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_mandatory')
                            ->label(__('stage_requirement_definition.fields.is_mandatory'))
                            ->default(true),
                        Select::make('min_document_type_id')
                            ->label(__('stage_requirement_definition.fields.min_document_type'))
                            ->relationship('minDocumentType', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('stage_requirement_definition.fields.description'))
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label(__('stage_requirement_definition.fields.sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('stage_requirement_definition.label'))
            ->heading(__('stage_requirement_definition.relation.title'))
            ->recordTitleAttribute('requirement_code')
            ->columns([
                TextColumn::make('requirement_code')
                    ->label(__('stage_requirement_definition.fields.requirement_code')),
                TextColumn::make('name_tr')
                    ->label(__('stage_requirement_definition.fields.name_tr')),
                TextColumn::make('evidence_type')
                    ->label(__('stage_requirement_definition.fields.evidence_type'))
                    ->badge(),
                IconColumn::make('is_mandatory')
                    ->label(__('stage_requirement_definition.fields.is_mandatory'))
                    ->boolean(),
                TextColumn::make('minDocumentType.name')
                    ->label(__('stage_requirement_definition.fields.min_document_type'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['stage_node_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(StageRequirementDefinitionService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (StageRequirementDefinition $record, array $data): Model {
                        try {
                            return app(StageRequirementDefinitionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (StageRequirementDefinition $record): bool {
                        try {
                            return app(StageRequirementDefinitionService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('stage_requirement_definition.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedListBullet);
    }
}
