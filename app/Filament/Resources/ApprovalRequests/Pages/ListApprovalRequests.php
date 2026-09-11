<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalRequests\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Query\Document\DocumentQueries;
use App\Query\WorkRequest\WorkRequestQueries;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Approval\Subjects\ApprovalSubjectRegistry;
use App\Services\Approval\Subjects\DocumentRevisionSubject;
use App\Services\Approval\Subjects\WorkRequestSubject;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use App\Query\Approval\ApprovalQueries;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListApprovalRequests extends ListRecords
{
    protected static string $resource = ApprovalRequestResource::class;

    /**
     * Onay talebi acma (11 Eylul 2026 kullanici istegi: "Onay Talepleri
     * icerisinden talep olusturamiyorum"): konu turu (talep ya da dokuman
     * revizyonu), kayit, politika ve not secilir; motor onaycilari cozer.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $personnelId = (int) auth()->id();

        return [
            Action::make('open_request')
                ->label(__('approval_request.actions.create'))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('primary')
                ->modalHeading(__('approval_request.actions.create'))
                ->modalDescription(__('approval_request.help.create'))
                ->modalWidth(Width::TwoExtraLarge)
                ->schema([
                    Select::make('subject_type')
                        ->label(__('approval_request.fields.subject_type'))
                        ->options(fn (): array => app(ApprovalSubjectRegistry::class)->options())
                        ->default(WorkRequestSubject::TYPE)
                        ->required()
                        ->live()
                        ->native(false),
                    Select::make('work_request_id')
                        ->label(__('approval_request.fields.subject_work_request'))
                        ->options(fn (): array => SchemaReadiness::hasBatch('B11B') ? app(WorkRequestQueries::class)->approvableOptions($personnelId) : [])
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('subject_type') === WorkRequestSubject::TYPE)
                        ->required(fn (Get $get): bool => $get('subject_type') === WorkRequestSubject::TYPE),
                    Select::make('document_revision_id')
                        ->label(__('approval_request.fields.subject_document_revision'))
                        ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('subject_type') === DocumentRevisionSubject::TYPE)
                        ->required(fn (Get $get): bool => $get('subject_type') === DocumentRevisionSubject::TYPE),
                    Select::make('policy_id')
                        ->label(__('approval_request.fields.policy'))
                        ->options(fn (Get $get): array => filled($get('subject_type')) ? app(ApprovalQueries::class)->publishedPolicyOptions((string) $get('subject_type')) : [])
                        ->required()
                        ->native(false),
                    Textarea::make('note')
                        ->label(__('approval_request.fields.note'))
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    $type = (string) $data['subject_type'];
                    $subjectId = $type === WorkRequestSubject::TYPE ? (int) ($data['work_request_id'] ?? 0) : (int) ($data['document_revision_id'] ?? 0);

                    try {
                        $approval = app(ApprovalRequestService::class)->request($type, $subjectId, null, (int) $data['policy_id'], $data['note'] ?? null);
                        DomainNotifications::success(__('approval_request.messages.created'));
                        $this->redirect(ApprovalRequestResource::getUrl('view', ['record' => $approval]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('approval_request.help.list');
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $queries = app(ApprovalQueries::class);
        $personnelId = (int) auth()->id();

        return [
            'inbox' => Tab::make(__('approval_request.tabs.inbox'))
                ->icon(Heroicon::OutlinedInbox)
                ->badge(fn (): ?string => ($count = $queries->inboxCount($personnelId)) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyInbox($query, $personnelId)),
            'mine' => Tab::make(__('approval_request.tabs.mine'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyMine($query, $personnelId)),
            'open' => Tab::make(__('approval_request.tabs.open'))
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query): Builder => $queries->applyOpen($query)),
            'all' => Tab::make(__('approval_request.tabs.all'))
                ->icon(Heroicon::OutlinedRectangleStack),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'inbox';
    }
}
