<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\ComponentDefinitions\ComponentDefinitionResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Activity\PersonnelActivity;
use App\Models\WorkRequest\WorkRequest;
use App\Models\Approval\ApprovalRequest;
use App\Query\Approval\ApprovalQueries;
use App\Query\WorkRequest\WorkRequestQueries;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Approval\Subjects\WorkRequestSubject;
use App\Services\Platform\SchemaReadiness;
use App\Services\WorkRequest\WorkRequestService;
use App\Support\ActivityLabels;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Talep karti: talep, taraflar, ilgili kayitlar (baglantili), kaynak sohbet
 * mesaji ve hareket gecmisi. Eylemler taraf ve duruma gore gorunur.
 */
class ViewWorkRequest extends ViewRecord
{
    protected static string $resource = WorkRequestResource::class;

    public function getTitle(): string
    {
        /** @var WorkRequest $request */
        $request = $this->getRecord();

        return $request->request_no.' · '.$request->title;
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var WorkRequest $request */
        $request = $this->getRecord();

        return $schema->columns(1)->components([
            Section::make(__('work_request.sections.request'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('request_no')->label(__('work_request.fields.request_no'))->badge()->color('gray'),
                    TextEntry::make('status')->label(__('work_request.fields.status'))->badge(),
                    TextEntry::make('priority')->label(__('work_request.fields.priority'))->badge(),
                    TextEntry::make('due_on')->label(__('work_request.fields.due_on'))->date('d.m.Y')->placeholder('-'),
                    TextEntry::make('created_at')->label(__('work_request.fields.created_at'))->dateTime('d.m.Y H:i'),
                    TextEntry::make('description')
                        ->label(__('work_request.fields.description'))
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])),
            Section::make(__('work_request.sections.parties'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('requester_label')
                        ->label(__('work_request.fields.from'))
                        ->state(fn (WorkRequest $record): string => $record->requesterLabel())
                        ->icon(Heroicon::OutlinedUserCircle),
                    TextEntry::make('target_label')
                        ->label(__('work_request.fields.to'))
                        ->state(fn (WorkRequest $record): string => $record->targetsOrgUnit() ? ($record->targetOrgUnit?->name ?? '-') : ($record->targetPersonnel?->full_name ?? '-'))
                        ->icon(fn (WorkRequest $record): Heroicon => $record->target_kind->getIcon()),
                    TextEntry::make('assignee.full_name')->label(__('work_request.fields.assignee'))->placeholder(__('work_request.values.unassigned')),
                    TextEntry::make('accepted_at')->label(__('work_request.fields.accepted_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                    TextEntry::make('closed_at')->label(__('work_request.fields.closed_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                    TextEntry::make('closedBy.full_name')->label(__('work_request.fields.closed_by'))->placeholder('-'),
                    TextEntry::make('closing_note')
                        ->label(__('work_request.fields.closing_note'))
                        ->placeholder('-')
                        ->visible(fn (WorkRequest $record): bool => filled($record->closing_note))
                        ->columnSpanFull(),
                ])),
            Section::make(__('work_request.sections.related'))
                ->icon(Heroicon::OutlinedLink)
                ->columns(FieldGrid::COLUMNS)
                ->visible(fn (WorkRequest $record): bool => $record->project_id !== null || $record->customer_party_id !== null || $record->component_definition_id !== null
                    || $record->proposal_id !== null || $record->business_case_id !== null || $record->contract_id !== null || $record->document_id !== null)
                ->components(FieldGrid::fields([
                    $this->relatedEntry('project.name', 'project', fn (WorkRequest $r) => $r->project, ProjectResource::class),
                    $this->relatedEntry('customer.display_name', 'customer', fn (WorkRequest $r) => $r->customer, PartyResource::class),
                    $this->relatedEntry('componentDefinition.name_tr', 'component', fn (WorkRequest $r) => $r->componentDefinition, ComponentDefinitionResource::class),
                    $this->relatedEntry('proposal.title', 'proposal', fn (WorkRequest $r) => $r->proposal, ProposalResource::class),
                    $this->relatedEntry('businessCase.title', 'business_case', fn (WorkRequest $r) => $r->businessCase, BusinessCaseResource::class),
                    $this->relatedEntry('contract.contract_no', 'contract', fn (WorkRequest $r) => $r->contract, ContractResource::class),
                    $this->relatedEntry('document.title', 'document', fn (WorkRequest $r) => $r->document, DocumentResource::class),
                ])),
            Section::make(__('work_request.sections.source'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->visible(fn (WorkRequest $record): bool => $record->source_message_id !== null && $record->sourceMessage !== null)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('sourceMessage.author.full_name')->label(__('work_request.fields.source_author'))->placeholder('-'),
                    TextEntry::make('sourceMessage.sent_at')->label(__('work_request.fields.source_sent_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                    TextEntry::make('open_chat')
                        ->label(__('work_request.fields.source_conversation'))
                        ->state(fn (): string => __('work_request.actions.open_chat'))
                        ->url(fn (WorkRequest $record): string => Dashboard::getUrl(['sohbet' => $record->sourceMessage?->conversation_id]))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->color('primary'),
                    TextEntry::make('sourceMessage.body')
                        ->label(__('work_request.fields.source_body'))
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])),
            Section::make(__('work_request.sections.approval'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn (WorkRequest $record): bool => SchemaReadiness::hasBatch('B07') && app(WorkRequestQueries::class)->approvalRequestsFor((int) $record->getKey())->isNotEmpty())
                ->components([
                    TextEntry::make('approvals')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->state(fn (WorkRequest $record): array => app(WorkRequestQueries::class)
                            ->approvalRequestsFor((int) $record->getKey())
                            ->map(fn (ApprovalRequest $approval): string => sprintf(
                                '%s · %s · %s',
                                $approval->requested_at?->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i') ?? '-',
                                $approval->status->getLabel(),
                                $approval->requester?->full_name ?? __('activity.system'),
                            ))
                            ->all()),
                    TextEntry::make('open_approval')
                        ->hiddenLabel()
                        ->state(fn (): string => __('work_request.actions.open_approval'))
                        ->url(fn (WorkRequest $record): ?string => ($latest = app(WorkRequestQueries::class)->approvalRequestsFor((int) $record->getKey())->first()) !== null
                            ? ApprovalRequestResource::getUrl('view', ['record' => $latest])
                            : null)
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->color('primary'),
                ]),
            Section::make(__('work_request.sections.history'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->collapsible()
                ->components([
                    TextEntry::make('history')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->state(fn (WorkRequest $record): array => app(WorkRequestQueries::class)
                            ->activitiesFor((int) $record->getKey())
                            ->map(fn (PersonnelActivity $activity): string => sprintf(
                                '%s · %s · %s%s',
                                $activity->occurred_at?->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i') ?? '-',
                                $activity->actorName(),
                                ActivityLabels::action($activity->action_code),
                                ($lines = ActivityLabels::changeLines($activity->changes)) !== [] ? ' — '.implode(' | ', $lines) : '',
                            ))
                            ->all())
                        ->placeholder(__('work_request.values.no_history')),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('accept')
                ->label(__('work_request.actions.accept'))
                ->icon(Heroicon::OutlinedPlayCircle)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('accept', $this->getRecord()))
                ->action(fn () => $this->run(fn (WorkRequestService $service) => $service->accept($this->getRecord()), 'accepted')),
            Action::make('complete')
                ->label(__('work_request.actions.complete'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => Gate::allows('complete', $this->getRecord()))
                ->schema([
                    Textarea::make('note')->label(__('work_request.fields.closing_note'))->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn (WorkRequestService $service) => $service->complete($this->getRecord(), $data['note'] ?? null), 'completed')),
            Action::make('reject')
                ->label(__('work_request.actions.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => Gate::allows('reject', $this->getRecord()))
                ->schema([
                    Textarea::make('note')->label(__('work_request.fields.closing_note'))->helperText(__('work_request.help.reject_note'))->required()->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn (WorkRequestService $service) => $service->reject($this->getRecord(), $data['note'] ?? null), 'rejected')),
            Action::make('reassign')
                ->label(__('work_request.actions.reassign'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('reassign', $this->getRecord()))
                ->schema([
                    Select::make('assignee_personnel_id')
                        ->label(__('work_request.fields.assignee'))
                        ->options(fn (): array => app(WorkRequestQueries::class)
                            ->unitMembers((int) $this->getRecord()->target_org_unit_id)
                            ->sortBy('full_name')
                            ->pluck('full_name', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->native(false),
                ])
                ->action(fn (array $data) => $this->run(fn (WorkRequestService $service) => $service->reassign($this->getRecord(), (int) $data['assignee_personnel_id']), 'reassigned')),
            Action::make('cancel')
                ->label(__('work_request.actions.cancel'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('cancel', $this->getRecord()))
                ->schema([
                    Textarea::make('note')->label(__('work_request.fields.closing_note'))->rows(2)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn (WorkRequestService $service) => $service->cancel($this->getRecord(), $data['note'] ?? null), 'cancelled')),
            Action::make('send_to_approval')
                ->label(__('work_request.actions.send_to_approval'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('warning')
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B07')
                    && $this->getRecord()->isOpen()
                    && Gate::allows('view', $this->getRecord())
                    && app(ApprovalQueries::class)->openRequestFor(WorkRequestSubject::TYPE, (int) $this->getRecord()->getKey()) === null)
                ->modalDescription(__('work_request.help.send_to_approval'))
                ->schema([
                    Select::make('policy_id')
                        ->label(__('work_request.fields.approval_policy'))
                        ->options(fn (): array => app(ApprovalQueries::class)->publishedPolicyOptions(WorkRequestSubject::TYPE))
                        ->required()
                        ->native(false),
                    Textarea::make('note')->label(__('work_request.fields.approval_note'))->rows(3)->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    try {
                        $approval = app(ApprovalRequestService::class)->request(WorkRequestSubject::TYPE, (int) $this->getRecord()->getKey(), null, (int) $data['policy_id'], $data['note'] ?? null);
                        DomainNotifications::success(__('work_request.messages.sent_to_approval'));
                        $this->redirect(ApprovalRequestResource::getUrl('view', ['record' => $approval]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
            EditAction::make()->label(__('work_request.actions.edit')),
        ];
    }

    private function relatedEntry(string $name, string $labelKey, callable $record, string $resource): TextEntry
    {
        return TextEntry::make($name)
            ->label(__('work_request.fields.'.$labelKey))
            ->visible(fn (WorkRequest $request): bool => $record($request) instanceof Model)
            ->url(function (WorkRequest $request) use ($record, $resource): ?string {
                $related = $record($request);

                try {
                    return $related instanceof Model ? $resource::getUrl('view', ['record' => $related]) : null;
                } catch (Throwable) {
                    return null;
                }
            })
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('primary');
    }

    /**
     * @param  callable(WorkRequestService): mixed  $operation
     */
    private function run(callable $operation, string $messageKey): void
    {
        try {
            $operation(app(WorkRequestService::class));
            DomainNotifications::success(__('work_request.messages.'.$messageKey));
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);
        }

        $this->redirect(WorkRequestResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
