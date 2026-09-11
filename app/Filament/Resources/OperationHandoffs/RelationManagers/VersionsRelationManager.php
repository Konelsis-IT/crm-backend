<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffs\RelationManagers;

use App\Enums\Acquisition\ReviewDecision;
use App\Exceptions\AbstractException;
use App\Filament\Resources\OperationHandoffVersions\OperationHandoffVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Query\Document\DocumentQueries;
use App\Services\Acquisition\OperationHandoffVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Model;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentDuplicate;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('operation_handoff_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('operation_handoff_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('proposal_version_id')
                            ->label(__('operation_handoff_version.fields.proposal_version'))
                            ->relationship(
                            'proposalVersion',
                            'version_no',
                            modifyQueryUsing: fn ($query) => $query->whereHas('proposal', fn ($q) => $q->where('business_case_id', $this->getOwnerRecord()->business_case_id)),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Acquisition\ProposalVersion $record): string => $record->proposal->proposal_no.' v'.$record->version_no)
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText(__('operation_handoff_version.help.proposal_version')),
                        Select::make('contract_version_id')
                            ->label(__('operation_handoff_version.fields.contract_version'))
                            ->relationship(
                            'contractVersion',
                            'version_no',
                            modifyQueryUsing: fn ($query) => $query->whereHas('contract', fn ($q) => $q->where('business_case_id', $this->getOwnerRecord()->business_case_id)),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Acquisition\ContractVersion $record): string => $record->contract->contract_no.' v'.$record->version_no)
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText(__('operation_handoff_version.help.contract_version')),
                        Select::make('manifest_document_revision_id')
                            ->label(__('operation_handoff_version.fields.manifest_document_revision'))
                            ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                            ->searchable()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('operation_handoff_version.label'))
            ->heading(__('operation_handoff_version.relation.title'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('operation_handoff_version.fields.version_no'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('operation_handoff_version.fields.status'))
                    ->badge(),
                TextColumn::make('proposalVersion.version_no')
                    ->label(__('operation_handoff_version.fields.proposal_version')),
                TextColumn::make('contractVersion.version_no')
                    ->label(__('operation_handoff_version.fields.contract_version'))
                    ->placeholder('-'),
                TextColumn::make('submitter.full_name')
                    ->label(__('operation_handoff_version.fields.submitter'))
                    ->placeholder('-'),
                TextColumn::make('submitted_at')
                    ->label(__('operation_handoff_version.fields.submitted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['operation_handoff_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(OperationHandoffVersionService::class)->create($data);
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
                    ->url(fn (OperationHandoffVersion $record): string => OperationHandoffVersionResource::getUrl('view', ['record' => $record])),
                Action::make('submit')
                    ->label(__('operation_handoff_version.actions.submit'))
                    ->color('primary')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->visible(fn (OperationHandoffVersion $record): bool => $record->status === \App\Enums\Acquisition\HandoffVersionStatus::Draft)
                    ->action(function (OperationHandoffVersion $record, array $data): void {
                        try {
                            app(\App\Services\Acquisition\OperationHandoffVersionService::class)->submit($record);
                            DomainNotifications::success(__('operation_handoff_version.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
                Action::make('review')
                    ->label(__('operation_handoff_version.actions.review'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->requiresConfirmation()
                    ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                Select::make('decision')
                    ->label(__('operation_handoff_version.fields.decision'))
                    ->options(ReviewDecision::class)
                    ->default(ReviewDecision::Accepted->value)
                    ->required()
                    ->native(false),
                Textarea::make('comment')
                    ->label(__('operation_handoff_version.fields.comment'))
                    ->columnSpanFull(),
                TextInput::make('project_name')
                    ->label(__('operation_handoff_version.fields.project_name'))
                    ->maxLength(255),
                Select::make('project_manager_employee_id')
                    ->label(__('operation_handoff_version.fields.project_manager'))
                    ->options(fn (): array => app(\App\Query\Personnel\PersonnelQueries::class)->personnelOptions())
                    ->searchable()
                    ->native(false),
                TextInput::make('site_location')
                    ->label(__('operation_handoff_version.fields.site_location'))
                    ->maxLength(100),
                DatePicker::make('planned_start_on')
                    ->label(__('operation_handoff_version.fields.planned_start_on'))
                    ->displayFormat('d.m.Y'),
                DatePicker::make('planned_finish_on')
                    ->label(__('operation_handoff_version.fields.planned_finish_on'))
                    ->displayFormat('d.m.Y'),
                    ])))
                    ->visible(fn (OperationHandoffVersion $record): bool => $record->status === \App\Enums\Acquisition\HandoffVersionStatus::Submitted)
                    ->action(function (OperationHandoffVersion $record, array $data): void {
                        try {
                            app(\App\Services\Acquisition\HandoffReviewService::class)->create([
                                'handoff_version_id' => $record->getKey(),
                                'decision' => $data['decision'],
                                'comment' => $data['comment'] ?? null,
                                'project_overrides' => [
                                    'name' => $data['project_name'] ?? null,
                                    'project_manager_employee_id' => $data['project_manager_employee_id'] ?? null,
                                    'site_location' => $data['site_location'] ?? null,
                                    'planned_start_on' => $data['planned_start_on'] ?? null,
                                    'planned_finish_on' => $data['planned_finish_on'] ?? null,
                                ],
                            ]);
                            DomainNotifications::success(__('operation_handoff_version.messages.reviewed'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('operation_handoff_version.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentDuplicate);
    }
}
