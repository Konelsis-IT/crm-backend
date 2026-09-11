<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\DecisionScope;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectDecision;
use App\Services\Project\ProjectDecisionService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Database\Eloquent\Model;

class DecisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'decisions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedScale;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_decision.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_decision.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('title')
                            ->label(__('project_decision.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('project_decision.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('decision_scope')
                            ->label(__('project_decision.fields.decision_scope'))
                            ->options(DecisionScope::class)
                            ->default(DecisionScope::Other->value)
                            ->required()
                            ->native(false),
                        DateTimePicker::make('decided_at')
                            ->label(__('project_decision.fields.decided_at')),
                        Select::make('document_revision_id')
                            ->label(__('project_decision.fields.document_revision'))
                            ->relationship('documentRevision', 'title')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Document\DocumentRevision $record): string => $record->document->document_no.' Rev.'.$record->revision_code)
                            ->searchable()
                            ->preload()
                            ->native(false),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('project_decision.label'))
            ->heading(__('project_decision.relation.title'))
            ->recordTitleAttribute('decision_no')
            ->columns([
                TextColumn::make('decision_no')
                    ->label(__('project_decision.fields.decision_no')),
                TextColumn::make('title')
                    ->label(__('project_decision.fields.title'))
                    ->limit(40),
                TextColumn::make('decision_scope')
                    ->label(__('project_decision.fields.decision_scope'))
                    ->badge(),
                TextColumn::make('decider.full_name')
                    ->label(__('project_decision.fields.decider')),
                TextColumn::make('decided_at')
                    ->label(__('project_decision.fields.decided_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProjectDecisionService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                
            ])
            ->toolbarActions([])
            ->defaultSort('decided_at', 'desc')
            ->emptyStateHeading(__('project_decision.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedScale);
    }
}
