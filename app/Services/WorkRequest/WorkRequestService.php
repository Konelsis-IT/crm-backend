<?php

declare(strict_types=1);

namespace App\Services\WorkRequest;

use App\Enums\WorkRequest\RequestTargetKind;
use App\Enums\WorkRequest\WorkRequestPriority;
use App\Enums\WorkRequest\WorkRequestStatus;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\WorkRequest\NoteRequiredException;
use App\Exceptions\WorkRequest\SelfTargetException;
use App\Exceptions\WorkRequest\TargetRequiredException;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Models\Personnel\Personnel;
use App\Models\WorkRequest\WorkRequest;
use App\Query\WorkRequest\WorkRequestQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Notification\PanelNotifier;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Talep yasam dongusu (D-84): olustur -> kabul et -> tamamla; ret ve iptal.
 * Her adim Personel Hareketleri'ne yazilir ve karsi tarafa zil bildirimi gider.
 */
final class WorkRequestService extends AbstractService
{
    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    /** @var list<string> */
    protected array $with = ['requester', 'requesterOrgUnit', 'targetPersonnel', 'targetOrgUnit', 'assignee'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly WorkRequestQueries $queries,
        private readonly PanelNotifier $notifier,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $me = $this->actorId();
        $data = $this->normalize($data);
        $this->assertTarget($data, $me);

        return $this->transactions->run(function () use ($data, $me): WorkRequest {
            /** @var WorkRequest $request */
            $request = parent::create([
                ...$data,
                'request_no' => 'TLP-TMP-'.bin2hex(random_bytes(6)),
                'requester_personnel_id' => $me,
                'status' => WorkRequestStatus::Open->value,
                'priority' => $data['priority'] ?? WorkRequestPriority::Normal->value,
                'assignee_personnel_id' => $data['target_kind'] === RequestTargetKind::Personnel->value ? $data['target_personnel_id'] : null,
            ]);

            $request->forceFill(['request_no' => sprintf('TLP-%06d', (int) $request->getKey())])->save();

            $this->notify($request, $this->targetRecipients($request), 'created');

            return $request->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var WorkRequest $request */
        $request = $this->show($record);

        if ($request->status !== WorkRequestStatus::Open) {
            throw InvalidTransitionException::make();
        }

        $data = $this->normalize($data);
        $this->assertTarget([...$request->only(['target_kind', 'target_personnel_id', 'target_org_unit_id']), ...$data], (int) $request->requester_personnel_id);

        unset($data['requester_personnel_id'], $data['status'], $data['request_no']);

        return parent::update($request, $data);
    }

    public function accept(Model|int|string $record): WorkRequest
    {
        return $this->transitionTo($record, WorkRequestStatus::InProgress, 'accepted', function (WorkRequest $request): void {
            $request->fill([
                'accepted_at' => Carbon::now('UTC'),
                'assignee_personnel_id' => $request->assignee_personnel_id ?? ($request->targetsOrgUnit() ? $this->actorId() : $request->target_personnel_id),
            ]);
        }, fn (WorkRequest $request) => $this->requesterRecipients($request));
    }

    public function complete(Model|int|string $record, ?string $note = null): WorkRequest
    {
        return $this->transitionTo($record, WorkRequestStatus::Done, 'completed', function (WorkRequest $request) use ($note): void {
            $now = Carbon::now('UTC');
            $request->fill([
                'completed_at' => $now,
                'closed_at' => $now,
                'closed_by_personnel_id' => $this->actorId(),
                'closing_note' => filled($note) ? trim((string) $note) : null,
                'assignee_personnel_id' => $request->assignee_personnel_id ?? $this->actorId(),
            ]);
        }, fn (WorkRequest $request) => $this->requesterRecipients($request));
    }

    public function reject(Model|int|string $record, ?string $note): WorkRequest
    {
        if (! filled($note)) {
            throw NoteRequiredException::make();
        }

        return $this->transitionTo($record, WorkRequestStatus::Rejected, 'rejected', function (WorkRequest $request) use ($note): void {
            $request->fill([
                'closed_at' => Carbon::now('UTC'),
                'closed_by_personnel_id' => $this->actorId(),
                'closing_note' => trim((string) $note),
            ]);
        }, fn (WorkRequest $request) => $this->requesterRecipients($request));
    }

    public function cancel(Model|int|string $record, ?string $note = null): WorkRequest
    {
        return $this->transitionTo($record, WorkRequestStatus::Cancelled, 'cancelled', function (WorkRequest $request) use ($note): void {
            $request->fill([
                'closed_at' => Carbon::now('UTC'),
                'closed_by_personnel_id' => $this->actorId(),
                'closing_note' => filled($note) ? trim((string) $note) : null,
            ]);
        }, fn (WorkRequest $request) => $this->targetRecipients($request));
    }

    /** Birime gelen talepte sorumlu kisiyi belirler / degistirir. */
    public function reassign(Model|int|string $record, int $assigneeId): WorkRequest
    {
        return $this->transactions->run(function () use ($record, $assigneeId): WorkRequest {
            /** @var WorkRequest $request */
            $request = $this->lockForUpdate($record);

            if (! $request->isOpen()) {
                throw InvalidTransitionException::make();
            }

            $previous = $request->assignee?->full_name;
            $request->fill(['assignee_personnel_id' => $assigneeId])->save();
            $request->load('assignee');

            $this->recordActivity($request, 'reassigned', [
                'sorumlu' => ['onceki' => $previous, 'yeni' => $request->assignee?->full_name],
            ]);

            $assignee = $request->assignee;

            if ($assignee instanceof Personnel) {
                $this->notify($request, collect([$assignee]), 'reassigned');
            }

            return $request;
        });
    }

    /**
     * Onay motoru sonucu (WorkRequestSubject): hareket kaydi ve talep edene
     * bildirim. Talebin durumu degismez; onay, muhatabin isi yapmasi icin
     * yetki verir.
     */
    public function noteApproval(?WorkRequest $request, string $event, int $approvalRequestId, ?string $comment = null): void
    {
        if ($request === null) {
            return;
        }

        $this->transactions->run(function () use ($request, $event, $approvalRequestId, $comment): void {
            $this->recordActivity($request, $event, array_filter([
                'talep_no' => $request->request_no,
                'onay_talebi' => $approvalRequestId,
                'yorum' => $comment,
            ], fn ($value): bool => $value !== null && $value !== ''));

            if (in_array($event, ['approval_approved', 'approval_rejected'], true)) {
                $request->load($this->with);
                $this->notify($request, $this->requesterRecipients($request)->merge($this->targetRecipients($request)), $event);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version'], $data['on_behalf_of_unit'], $data['requester_name']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $no = (string) $record->getAttribute('request_no');

        // Numara kayittan sonra verilir; hareket ozeti kesin numarayi tasisin.
        if (str_starts_with($no, 'TLP-TMP-')) {
            $no = sprintf('TLP-%06d', (int) $record->getKey());
        }

        return [
            'talep_no' => $no,
            'baslik' => $record->getAttribute('title'),
            'kime' => $record instanceof WorkRequest ? $record->targetLabel() : null,
        ];
    }

    /**
     * @param  callable(WorkRequest): void  $mutate
     * @param  callable(WorkRequest): \Illuminate\Support\Collection<int, Personnel>  $recipients
     */
    private function transitionTo(Model|int|string $record, WorkRequestStatus $target, string $operation, callable $mutate, callable $recipients): WorkRequest
    {
        return $this->transactions->run(function () use ($record, $target, $operation, $mutate, $recipients): WorkRequest {
            /** @var WorkRequest $request */
            $request = $this->lockForUpdate($record);
            $from = $request->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make();
            }

            $mutate($request);
            $request->fill(['status' => $target->value])->save();
            $request->load($this->with);

            $this->recordActivity($request, $operation, [
                'durum' => ['onceki' => $from->getLabel(), 'yeni' => $target->getLabel()],
                'talep_no' => $request->request_no,
            ]);

            $this->notify($request, $recipients($request), $operation);

            return $request;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $onBehalf = (bool) ($data['on_behalf_of_unit'] ?? false);

        foreach (['requester_org_unit_id', 'target_personnel_id', 'target_org_unit_id', 'project_id', 'customer_party_id', 'component_definition_id', 'proposal_id', 'business_case_id', 'contract_id', 'document_id', 'source_message_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = filled($data[$key]) ? (int) $data[$key] : null;
            }
        }

        if (array_key_exists('on_behalf_of_unit', $data) && ! $onBehalf) {
            $data['requester_org_unit_id'] = null;
        }

        if (array_key_exists('target_kind', $data)) {
            $data['target_kind'] = (string) $data['target_kind'];

            if ($data['target_kind'] === RequestTargetKind::Personnel->value) {
                $data['target_org_unit_id'] = null;
            } elseif ($data['target_kind'] === RequestTargetKind::OrgUnit->value) {
                $data['target_personnel_id'] = null;
            }
        }

        if (array_key_exists('title', $data)) {
            $data['title'] = trim((string) $data['title']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTarget(array $data, int $requesterId): void
    {
        $kind = RequestTargetKind::tryFrom((string) ($data['target_kind'] ?? ''));

        if ($kind === null) {
            throw TargetRequiredException::make();
        }

        if ($kind === RequestTargetKind::Personnel) {
            $targetId = (int) ($data['target_personnel_id'] ?? 0);

            if ($targetId <= 0) {
                throw TargetRequiredException::make();
            }

            if ($targetId === $requesterId) {
                throw SelfTargetException::make();
            }

            return;
        }

        if ((int) ($data['target_org_unit_id'] ?? 0) <= 0) {
            throw TargetRequiredException::make();
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Personnel>
     */
    private function targetRecipients(WorkRequest $request): \Illuminate\Support\Collection
    {
        if ($request->targetsOrgUnit()) {
            return $this->queries->unitMembers((int) $request->target_org_unit_id)->values();
        }

        $target = $request->targetPersonnel;

        return collect($target instanceof Personnel ? [$target] : []);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Personnel>
     */
    private function requesterRecipients(WorkRequest $request): \Illuminate\Support\Collection
    {
        $recipients = collect();
        $requester = $request->requester;

        if ($requester instanceof Personnel) {
            $recipients->push($requester);
        }

        $unitManager = $request->requesterOrgUnit?->manager;

        if ($unitManager instanceof Personnel && $unitManager->isActive()) {
            $recipients->push($unitManager);
        }

        return $recipients->unique(fn (Personnel $personnel) => $personnel->getKey())->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Personnel>  $recipients
     */
    private function notify(WorkRequest $request, \Illuminate\Support\Collection $recipients, string $event): void
    {
        $me = $this->actor->personnelId();
        $recipients = $recipients->reject(fn (Personnel $personnel): bool => $me !== null && (int) $personnel->getKey() === $me);

        if ($recipients->isEmpty()) {
            return;
        }

        $actions = [];

        try {
            $actions[] = Action::make('open')
                ->label(__('work_request.actions.open'))
                ->button()
                ->url(WorkRequestResource::getUrl('view', ['record' => $request]))
                ->markAsRead();
        } catch (Throwable) {
            // Rota yoksa dugmesiz bildirim.
        }

        $this->notifier->send(
            $recipients,
            __('work_request.notifications.'.$event.'.title', ['no' => $request->request_no]),
            __('work_request.notifications.'.$event.'.body', [
                'title' => $request->title,
                'from' => $request->requesterLabel(),
                'to' => $request->targetLabel(),
            ]),
            match ($event) {
                'completed', 'approval_approved' => Heroicon::OutlinedCheckCircle,
                'rejected', 'approval_rejected' => Heroicon::OutlinedXCircle,
                'cancelled' => Heroicon::OutlinedNoSymbol,
                default => Heroicon::OutlinedInboxArrowDown,
            },
            match ($event) {
                'completed', 'approval_approved' => 'success',
                'rejected', 'cancelled', 'approval_rejected' => 'danger',
                default => 'warning',
            },
            $actions,
        );
    }

    private function actorId(): int
    {
        $id = $this->actor->personnelId();

        if ($id === null) {
            throw ActorRequiredException::make();
        }

        return $id;
    }
}
