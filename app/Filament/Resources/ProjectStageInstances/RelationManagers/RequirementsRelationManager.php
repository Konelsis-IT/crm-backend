<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectStageInstances\RelationManagers;

use App\Enums\Project\Applicability;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectStageRequirement;
use App\Query\Document\DocumentQueries;
use App\Services\Project\ProjectStageRequirementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'requirements';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedListBullet;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_stage_requirement.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_stage_requirement.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('applicability')
                            ->label(__('project_stage_requirement.fields.applicability'))
                            ->options(Applicability::class)
                            ->default(Applicability::Applicable->value)
                            ->required()
                            ->native(false),
                        Select::make('owner_personnel_id')
                            ->label(__('project_stage_requirement.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('due_at')
                            ->label(__('project_stage_requirement.fields.due_at'))
                            ->displayFormat('d.m.Y'),
                        Textarea::make('outcome_note')
                            ->label(__('project_stage_requirement.fields.outcome_note'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('project_stage_requirement.relation.title'))
            ->recordTitleAttribute('requirement_code_snapshot')
            ->columns([
                TextColumn::make('requirement_code_snapshot')
                    ->label(__('project_stage_requirement.fields.requirement_code_snapshot')),
                TextColumn::make('name_snapshot_tr')
                    ->label(__('project_stage_requirement.fields.name_snapshot_tr'))
                    ->limit(40),
                TextColumn::make('evidence_type_snapshot')
                    ->label(__('project_stage_requirement.fields.evidence_type_snapshot'))
                    ->badge(),
                IconColumn::make('is_mandatory_snapshot')
                    ->label(__('project_stage_requirement.fields.is_mandatory_snapshot'))
                    ->boolean(),
                TextColumn::make('applicability')
                    ->label(__('project_stage_requirement.fields.applicability'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('project_stage_requirement.fields.status'))
                    ->badge(),
                TextColumn::make('due_at')
                    ->label(__('project_stage_requirement.fields.due_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectStageRequirement $record, array $data): Model {
                        try {
                            return app(ProjectStageRequirementService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('add_evidence')
                    ->label(__('project_stage_requirement.actions.add_evidence'))
                    ->color('primary')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->requiresConfirmation()
                    ->schema([
                Select::make('document_revision_id')
                    ->label(__('project_stage_requirement.fields.document_revision'))
                    ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                    ->searchable()
                    ->required()
                    ->native(false),
                    ])
                    ->action(function (ProjectStageRequirement $record, array $data): void {
                        try {
                            app(\App\Services\Project\StageEvidenceService::class)->create(['project_stage_requirement_id' => $record->getKey(), 'document_revision_id' => $data['document_revision_id']]);
                            DomainNotifications::success(__('project_stage_requirement.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id')
            ->emptyStateHeading(__('project_stage_requirement.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedListBullet);
    }
}
