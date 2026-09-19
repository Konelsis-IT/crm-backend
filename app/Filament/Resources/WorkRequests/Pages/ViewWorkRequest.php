<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\Pages;

use App\Enums\WorkRequest\RequestTargetKind;
use App\Enums\WorkRequest\WorkRequestStatus;
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
use App\Models\Approval\ApprovalRequest;
use App\Models\WorkRequest\WorkRequest;
use App\Livewire\WorkRequestThread;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Query\WorkRequest\WorkRequestQueries;
use App\Services\Platform\SchemaReadiness;
use App\Services\WorkRequest\WorkRequestService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Talep karti: "Talep" ve "Taraflar" yan yana (yarim genislik), altinda
 * ilgili kayitlar ve onay, gerekirse kaynak sohbet mesaji; hareket gecmisi
 * ayri bir tablo (ActivitiesRelationManager). Eylemler tarafa ve duruma gore:
 * muhatap kabul eder / tamamlar / reddeder, talep eden iptal eder ve onay
 * merciini belirler (D-87); talep eden kendi talebini yonetemez.
 */
class ViewWorkRequest extends ViewRecord
{
    protected static string $resource = WorkRequestResource::class;

    private const HALF = ['default' => 1, 'md' => 2];

    public function getTitle(): string
    {
        /** @var WorkRequest $request */
        $request = $this->getRecord();

        return $request->request_no.' · '.$request->title;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 2])->components([
                Section::make(__('work_request.sections.request'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(self::HALF)
                    ->components([
                        TextEntry::make('request_no')->label(__('work_request.fields.request_no'))->badge()->color('gray'),
                        TextEntry::make('status')->label(__('work_request.fields.status'))->badge(),
                        TextEntry::make('priority')->label(__('work_request.fields.priority'))->badge(),
                        TextEntry::make('requires_approval')
                            ->label(__('work_request.fields.requires_approval'))
                            ->state(fn (): string => __('work_request.values.approval_required'))
                            ->badge()
                            ->color('primary')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->visible(fn (WorkRequest $record): bool => (bool) $record->requires_approval),
                        TextEntry::make('due_on')->label(__('work_request.fields.due_on'))->date('d.m.Y')->placeholder('-'),
                        TextEntry::make('created_at')->label(__('work_request.fields.created_at'))->dateTime('d.m.Y H:i'),
                        TextEntry::make('description')
                            ->label(__('work_request.fields.description'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('work_request.sections.parties'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->columns(self::HALF)
                    ->components([
                        TextEntry::make('requester_label')
                            ->label(__('work_request.fields.from'))
                            ->state(fn (WorkRequest $record): string => $record->requesterLabel())
                            ->icon(Heroicon::OutlinedUserCircle),
                        TextEntry::make('target_label')
                            ->label(__('work_request.fields.to'))
                            ->state(fn (WorkRequest $record): string => $record->targetsOrgUnit() ? ($record->targetOrgUnit?->name ?? '-') : ($record->targetPersonnel?->full_name ?? '-'))
                            ->icon(fn (WorkRequest $record): Heroicon => $record->target_kind->getIcon()),
                        TextEntry::make('assignee.full_name')->label(__('work_request.fields.assignee'))->placeholder(__('work_request.values.unassigned')),
                        TextEntry::make('approver.full_name')
                            ->label(__('work_request.fields.approver'))
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->placeholder('-')
                            ->visible(fn (WorkRequest $record): bool => (bool) $record->requires_approval),
                        TextEntry::make('accepted_at')->label(__('work_request.fields.accepted_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                        TextEntry::make('closed_at')->label(__('work_request.fields.closed_at'))->dateTime('d.m.Y H:i')->placeholder('-'),
                        TextEntry::make('closedBy.full_name')
                            ->label(__('work_request.fields.closed_by'))
                            ->placeholder('-')
                            ->visible(fn (WorkRequest $record): bool => $record->closed_at !== null),
                        TextEntry::make('closing_note')
                            ->label(__('work_request.fields.closing_note'))
                            ->placeholder('-')
                            ->visible(fn (WorkRequest $record): bool => filled($record->closing_note))
                            ->columnSpanFull(),
                    ]),
            ]),
            // Yazisma (B32): talep ilk mesaj, cevaplar altinda; sohbetten ayridir.
            Section::make(__('work_request.sections.thread'))
                ->description(__('work_request.help.thread'))
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B32'))
                ->components([
                    Livewire::make(WorkRequestThread::class, fn (WorkRequest $record): array => ['requestId' => (int) $record->getKey()])
                        ->key('work-request-thread'),
                ]),
            Grid::make(['default' => 1, 'lg' => 2])->components([
                Section::make(__('work_request.sections.related'))
                    ->icon(Heroicon::OutlinedLink)
                    ->columns(self::HALF)
                    ->visible(fn (WorkRequest $record): bool => $record->project_id !== null || $record->customer_party_id !== null || $record->component_definition_id !== null
                        || $record->proposal_id !== null || $record->business_case_id !== null || $record->contract_id !== null || $record->document_id !== null)
                    ->components([
                        $this->relatedEntry('project.name', 'project', fn (WorkRequest $r) => $r->project, ProjectResource::class),
                        $this->relatedEntry('customer.display_name', 'customer', fn (WorkRequest $r) => $r->customer, PartyResource::class),
                        $this->relatedEntry('componentDefinition.name_tr', 'component', fn (WorkRequest $r) => $r->componentDefinition, ComponentDefinitionResource::class),
                        $this->relatedEntry('proposal.title', 'proposal', fn (WorkRequest $r) => $r->proposal, ProposalResource::class),
                        $this->relatedEntry('businessCase.title', 'business_case', fn (WorkRequest $r) => $r->businessCase, BusinessCaseResource::class),
                        $this->relatedEntry('contract.contract_no', 'contract', fn (WorkRequest $r) => $r->contract, ContractResource::class),
                        $this->relatedEntry('document.title', 'document', fn (WorkRequest $r) => $r->document, DocumentResource::class),
                    ]),
                Section::make(__('work_request.sections.approval'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->visible(fn (WorkRequest $record): bool => SchemaReadiness::hasBatch('B07') && app(WorkRequestQueries::class)->approvalRequestsFor((int) $record->getKey())->isNotEmpty())
                    ->components([
                        TextEntry::make('approvals')
                            ->label(__('work_request.sections.approval'))
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
                            ->label(__('work_request.actions.open_approval'))
                            ->hiddenLabel()
                            ->state(fn (): string => __('work_request.actions.open_approval'))
                            ->url(fn (WorkRequest $record): ?string => ($latest = app(WorkRequestQueries::class)->approvalRequestsFor((int) $record->getKey())->first()) !== null
                                ? ApprovalRequestResource::getUrl('view', ['record' => $latest])
                                : null)
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->color('primary'),
                    ]),
            ]),
            Section::make(__('work_request.sections.source'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->visible(fn (WorkRequest $record): bool => $record->source_message_id !== null && $record->sourceMessage !== null)
                ->columns(['default' => 1, 'md' => 3])
                ->components([
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
                ->modalDescription(__('work_request.help.accept'))
                ->visible(fn (): bool => Gate::allows('accept', $this->getRecord()))
                ->action(fn () => $this->run(fn (WorkRequestService $service) => $service->accept($this->getRecord()), 'accepted')),
            Action::make('complete')
                ->label(__('work_request.actions.complete'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->modalDescription(fn (): string => $this->getRecord()->needsApproval() && SchemaReadiness::hasBatch('B11D')
                    ? __('work_request.help.complete_with_approval', ['approver' => $this->getRecord()->approver?->full_name ?? '-'])
                    : __('work_request.help.complete'))
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
            // Yonlendirme (B32): talep muhatabi degilse baska kisiye / birime devredilir.
            Action::make('forward')
                ->label(__('work_request.actions.forward'))
                ->icon(Heroicon::OutlinedArrowUturnRight)
                ->color('gray')
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B32') && Gate::allows('forward', $this->getRecord()))
                ->modalHeading(__('work_request.actions.forward'))
                ->modalDescription(__('work_request.help.forward'))
                ->schema([
                    Select::make('target_kind')
                        ->label(__('work_request.fields.target_kind'))
                        ->options(RequestTargetKind::options())
                        ->default(RequestTargetKind::Personnel->value)
                        ->required()
                        ->live()
                        ->native(false),
                    Select::make('target_personnel_id')
                        ->label(__('work_request.fields.target_personnel'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('target_kind') === RequestTargetKind::Personnel->value)
                        ->required(fn (Get $get): bool => $get('target_kind') === RequestTargetKind::Personnel->value),
                    Select::make('target_org_unit_id')
                        ->label(__('work_request.fields.target_org_unit'))
                        ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('target_kind') === RequestTargetKind::OrgUnit->value)
                        ->required(fn (Get $get): bool => $get('target_kind') === RequestTargetKind::OrgUnit->value),
                    Textarea::make('reason')
                        ->label(__('work_request.thread.forward_reason'))
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->run(fn (WorkRequestService $service) => $service->forward(
                    $this->getRecord(),
                    (string) $data['target_kind'],
                    filled($data['target_personnel_id'] ?? null) ? (int) $data['target_personnel_id'] : null,
                    filled($data['target_org_unit_id'] ?? null) ? (int) $data['target_org_unit_id'] : null,
                    $data['reason'] ?? null,
                ), 'forwarded')),
            // Onaya tabi yapma (D-87): onay mercii secilir; onay talebi muhatap
            // isi tamamladiginda acilir. Olustururken isaretlenmediyse buradan.
            Action::make('send_to_approval')
                ->label(__('work_request.actions.send_to_approval'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('warning')
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B11D')
                    && SchemaReadiness::hasBatch('B07')
                    && Gate::allows('designateApprover', $this->getRecord()))
                ->modalHeading(__('work_request.actions.send_to_approval'))
                ->modalDescription(__('work_request.help.send_to_approval'))
                ->schema([
                    Select::make('approver_personnel_id')
                        ->label(__('work_request.fields.approver'))
                        ->options(function (): array {
                            /** @var WorkRequest $request */
                            $request = $this->getRecord();
                            $options = app(PersonnelQueries::class)->personnelOptions();
                            unset($options[(int) $request->requester_personnel_id]);

                            if ($request->target_personnel_id !== null) {
                                unset($options[(int) $request->target_personnel_id]);
                            }

                            return $options;
                        })
                        ->required()
                        ->searchable()
                        ->native(false),
                ])
                ->action(fn (array $data) => $this->run(fn (WorkRequestService $service) => $service->designateApprover($this->getRecord(), (int) $data['approver_personnel_id']), 'approver_designated')),
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
            $result = $operation(app(WorkRequestService::class));

            // Onaya tabi talep (D-87): tamamlama onaya sunulmus olabilir.
            if ($messageKey === 'completed' && $result instanceof WorkRequest && $result->status === WorkRequestStatus::AwaitingApproval) {
                $messageKey = 'awaiting_approval';
            }

            DomainNotifications::success(__('work_request.messages.'.$messageKey));
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);
        }

        $this->redirect(WorkRequestResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
