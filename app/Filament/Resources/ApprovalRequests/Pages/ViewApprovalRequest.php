<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalRequests\Pages;

use App\Enums\Approval\ApprovalDecisionKind;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Approval\ApprovalRequest;
use App\Query\Approval\ApprovalQueries;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Approval\Subjects\ApprovalSubjectRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Onay talebi karti: konu (baglantili), politika, talep sahibi, durum,
 * su an kimde; altta adimlar ve kararlar. Karar dugmeleri yalniz aktif
 * adimdaki onaycida (ya da gecerli vekilinde) gorunur.
 */
class ViewApprovalRequest extends ViewRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    /**
     * Bildirimdeki "Reddet" / "Iade et" baglantisi (?karar=reject|return|approve)
     * karar penceresini acik getirir (D-82). Yetkisi yoksa sayfa normal acilir.
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);

        $decision = request()->query('karar');

        if (
            is_string($decision)
            && in_array($decision, ['approve', 'reject', 'return'], true)
            && app(ApprovalQueries::class)->canDecide($this->getRecord(), (int) auth()->id())
        ) {
            $this->mountAction($decision);
        }
    }

    public function getTitle(): string
    {
        /** @var ApprovalRequest $request */
        $request = $this->getRecord();

        return (string) $request->subject_label;
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var ApprovalRequest $request */
        $request = $this->getRecord();
        $subjectUrl = $this->subjectUrl($request);
        $waitingOn = $request->activeSteps()->with('approver')->get()
            ->map(fn ($row): string => $row->approver?->full_name ?? __('approval_request.values.unresolved'))
            ->unique()
            ->implode(', ');

        $callout = match (true) {
            $request->status->isOpen() => Callout::make(__('approval_request.callouts.open', ['who' => $waitingOn !== '' ? $waitingOn : '-']))->info()->icon(Heroicon::OutlinedClock),
            $request->status === \App\Enums\Approval\ApprovalRequestStatus::Approved => Callout::make(__('approval_request.callouts.approved'))->success()->icon(Heroicon::OutlinedCheckCircle),
            $request->status === \App\Enums\Approval\ApprovalRequestStatus::Rejected => Callout::make(__('approval_request.callouts.rejected'))->danger()->icon(Heroicon::OutlinedXCircle),
            default => Callout::make(__('approval_request.callouts.closed', ['status' => $request->status->getLabel()]))->warning()->icon(Heroicon::OutlinedNoSymbol),
        };

        return $schema->columns(1)->components([
            $callout,
            Section::make(__('approval_request.sections.summary'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->components([
                    Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->components([
                        TextEntry::make('subject_label')
                            ->label(__('approval_request.fields.subject'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->iconColor('primary')
                            ->weight(FontWeight::SemiBold)
                            ->color($subjectUrl !== null ? 'primary' : 'gray')
                            ->url($subjectUrl)
                            ->columnSpan(2),
                        TextEntry::make('status')
                            ->label(__('approval_request.fields.status'))
                            ->badge(),
                        TextEntry::make('policyVersion.policy.code')
                            ->label(__('approval_request.fields.policy'))
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (string $state): string => $state.' · v'.($request->policyVersion?->version_no ?? '-')),
                        TextEntry::make('requester.full_name')
                            ->label(__('approval_request.fields.requester'))
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->iconColor('gray'),
                        TextEntry::make('requested_at')
                            ->label(__('approval_request.fields.requested_at'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->iconColor('gray')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('policyVersion.mode')
                            ->label(__('approval_policy_version.fields.mode'))
                            ->badge()
                            ->color('info'),
                        TextEntry::make('decided_at')
                            ->label(__('approval_request.fields.decided_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->iconColor('gray')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('invalidation_reason')
                            ->label(__('approval_request.fields.invalidation_reason'))
                            ->badge()
                            ->color('warning')
                            ->visible($request->invalidation_reason !== null),
                        TextEntry::make('note')
                            ->label(__('approval_request.fields.note'))
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(filled($request->note)),
                    ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->decisionAction('approve', ApprovalDecisionKind::Approved, 'success', Heroicon::OutlinedCheckCircle),
            $this->decisionAction('reject', ApprovalDecisionKind::Rejected, 'danger', Heroicon::OutlinedXCircle),
            $this->decisionAction('return', ApprovalDecisionKind::Returned, 'warning', Heroicon::OutlinedArrowUturnLeft),
            Action::make('cancel')
                ->label(__('approval_request.actions.cancel'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('cancel', $this->getRecord()))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('approval_request.fields.reason'))
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    try {
                        app(ApprovalRequestService::class)->cancel($this->getRecord(), $data['reason'] ?? null);
                        DomainNotifications::success(__('approval_request.messages.cancelled'));
                        $this->redirect(ApprovalRequestResource::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }

    private function decisionAction(string $name, ApprovalDecisionKind $kind, string $color, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label(__('approval_request.actions.'.$name))
            ->icon($icon)
            ->color($color)
            ->visible(fn (): bool => app(ApprovalQueries::class)->canDecide($this->getRecord(), (int) auth()->id()))
            ->modalHeading(__('approval_request.actions.'.$name))
            ->modalDescription(fn (): string => (string) $this->getRecord()->subject_label)
            ->schema([
                Textarea::make('comment')
                    ->label(__('approval_request.fields.comment'))
                    ->helperText($kind->requiresComment() ? __('approval_request.help.comment_required') : null)
                    ->required($kind->requiresComment())
                    ->maxLength(1000),
            ])
            ->action(function (array $data) use ($kind): void {
                try {
                    app(ApprovalRequestService::class)->decide($this->getRecord(), $kind, $data['comment'] ?? null);
                    DomainNotifications::success(__('approval_request.messages.decided'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $this->redirect(ApprovalRequestResource::getUrl('view', ['record' => $this->getRecord()]));
            });
    }

    private function subjectUrl(ApprovalRequest $request): ?string
    {
        try {
            return app(ApprovalSubjectRegistry::class)
                ->for($request->subject_type)
                ->resolve((int) $request->subject_id, $request->subject_revision_id)?->url;
        } catch (Throwable) {
            return null;
        }
    }
}
