<?php

declare(strict_types=1);

namespace App\Services\WorkRequest;

use App\Enums\WorkRequest\RequestTargetKind;
use App\Enums\WorkRequest\WorkRequestPriority;
use App\Enums\WorkRequest\WorkRequestStatus;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\WorkRequest\ApprovalPolicyMissingException;
use App\Exceptions\WorkRequest\ApproverInvalidException;
use App\Exceptions\WorkRequest\ApproverRequiredException;
use App\Exceptions\WorkRequest\ForwardNotAllowedException;
use App\Exceptions\WorkRequest\MessageEmptyException;
use App\Exceptions\WorkRequest\NoteRequiredException;
use App\Exceptions\WorkRequest\RequesterCannotHandleException;
use App\Exceptions\WorkRequest\SelfTargetException;
use App\Exceptions\WorkRequest\TargetRequiredException;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Models\Personnel\Personnel;
use App\Models\WorkRequest\WorkRequest;
use App\Models\WorkRequest\WorkRequestMessage;
use App\Query\Approval\ApprovalQueries;
use App\Query\WorkRequest\WorkRequestQueries;
use App\Services\AbstractService;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Approval\Subjects\WorkRequestSubject;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Document\FileObjectService;
use App\Services\Notification\PanelNotifier;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Talep yasam dongusu (D-84): olustur -> kabul et -> tamamla; ret ve iptal.
 * Onaya tabi talepte (D-87, B11D) tamamlama "onay bekliyor"a gecer ve secilen
 * onay mercii icin onay motoru talebi acilir; onaylaninca kapanir, reddedilirse
 * muhataba doner. Her adim Personel Hareketleri'ne yazilir ve karsi tarafa
 * zil bildirimi gider.
 */
final class WorkRequestService extends AbstractService
{
    /** Onaya tabi talebin kullandigi politika kodu (ApprovalPolicySeeder). */
    public const DESIGNATED_APPROVAL_POLICY = 'WORK_REQUEST_DESIGNATED';

    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    /** @var list<string> */
    protected array $with = ['requester', 'requesterOrgUnit', 'targetPersonnel', 'targetOrgUnit', 'assignee', 'approver'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly WorkRequestQueries $queries,
        private readonly ApprovalQueries $approvals,
        private readonly PanelNotifier $notifier,
        private readonly FileObjectService $fileObjects,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $me = $this->actorId();
        $files = $this->pullAttachments($data);
        $data = $this->normalize($data);
        $this->assertTarget($data, $me);
        $this->assertApprover($data, $me);

        return $this->transactions->run(function () use ($data, $me, $files): WorkRequest {
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

            // Talep acilirken eklenen dosyalar ilk mesajin parcasidir (B32).
            if ($files !== []) {
                $this->storeMessage($request, WorkRequestMessage::KIND_INITIAL, '', $files);
            }

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
        $merged = [...$request->only(['target_kind', 'target_personnel_id', 'target_org_unit_id', 'requires_approval', 'approver_personnel_id']), ...$data];
        $this->assertTarget($merged, (int) $request->requester_personnel_id);
        $this->assertApprover($merged, (int) $request->requester_personnel_id);

        unset($data['requester_personnel_id'], $data['status'], $data['request_no']);

        return parent::update($request, $data);
    }

    public function accept(Model|int|string $record): WorkRequest
    {
        return $this->transitionTo($record, WorkRequestStatus::InProgress, 'accepted', function (WorkRequest $request): void {
            $this->assertNotRequester($request);
            $request->fill([
                'accepted_at' => Carbon::now('UTC'),
                'assignee_personnel_id' => $request->assignee_personnel_id ?? ($request->targetsOrgUnit() ? $this->actorId() : $request->target_personnel_id),
            ]);
        }, fn (WorkRequest $request) => $this->requesterRecipients($request));
    }

    /**
     * Muhatap isi tamamlar. Onaya tabi talepte (D-87) talep kapanmaz; "onay
     * bekliyor"a gecer ve secilen onay mercii icin onay talebi acilir.
     */
    public function complete(Model|int|string $record, ?string $note = null): WorkRequest
    {
        /** @var WorkRequest $current */
        $current = $this->show($record);
        $this->assertNotRequester($current);

        if (! SchemaReadiness::hasBatch('B11D') || ! $current->needsApproval()) {
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

        $policyId = $this->approvals->policyIdByCode(self::DESIGNATED_APPROVAL_POLICY);

        if ($policyId === null) {
            throw ApprovalPolicyMissingException::make(['policy' => self::DESIGNATED_APPROVAL_POLICY]);
        }

        return $this->transactions->run(function () use ($record, $note, $policyId): WorkRequest {
            $request = $this->transitionTo($record, WorkRequestStatus::AwaitingApproval, 'awaiting_approval', function (WorkRequest $request) use ($note): void {
                $request->fill([
                    'completed_at' => Carbon::now('UTC'),
                    'closing_note' => filled($note) ? trim((string) $note) : null,
                    'assignee_personnel_id' => $request->assignee_personnel_id ?? $this->actorId(),
                ]);
            }, fn (WorkRequest $request) => $this->requesterRecipients($request));

            $approval = app(ApprovalRequestService::class)->request(
                WorkRequestSubject::TYPE,
                (int) $request->getKey(),
                null,
                $policyId,
                $request->closing_note,
            );

            $request->forceFill(['approval_request_id' => (int) $approval->getKey()])->save();

            return $request->refresh()->load($this->with);
        });
    }

    public function reject(Model|int|string $record, ?string $note): WorkRequest
    {
        if (! filled($note)) {
            throw NoteRequiredException::make();
        }

        return $this->transitionTo($record, WorkRequestStatus::Rejected, 'rejected', function (WorkRequest $request) use ($note): void {
            $this->assertNotRequester($request);
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

    /**
     * Onaya tabi yapma (D-87): acik talepte onay merciini belirler. Onay
     * talebi muhatap isi tamamladiginda acilir; onay mercii bilgilendirilir.
     */
    public function designateApprover(Model|int|string $record, int $approverId): WorkRequest
    {
        if (! SchemaReadiness::hasBatch('B11D')) {
            throw InvalidTransitionException::make();
        }

        return $this->transactions->run(function () use ($record, $approverId): WorkRequest {
            /** @var WorkRequest $request */
            $request = $this->lockForUpdate($record);

            if (! $request->isOpen() || $request->requires_approval) {
                throw InvalidTransitionException::make();
            }

            $this->assertApprover([
                'requires_approval' => true,
                'approver_personnel_id' => $approverId,
                'target_personnel_id' => $request->target_personnel_id,
            ], (int) $request->requester_personnel_id);

            $request->fill(['requires_approval' => true, 'approver_personnel_id' => $approverId])->save();
            $request->load($this->with);

            $this->recordActivity($request, 'approver_designated', [
                'talep_no' => $request->request_no,
                'onay_mercii' => $request->approver?->full_name,
            ]);

            $approver = $request->approver;

            if ($approver instanceof Personnel) {
                $this->notify($request, collect([$approver]), 'approver_designated');
            }

            return $request;
        });
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
     * Talebe cevap yazar (B32). Talep ilk mesajdir; cevap ikinci mesaj olarak
     * gorunur. `$files`: `local` diskteki gecici anahtar => orijinal ad.
     * Talep aciksa taraflar (talep eden, muhatap, sorumlu, onay mercii) yazar;
     * hareket gecmisine ve diger taraflarin zil bildirimine dusulur.
     *
     * @param  array<string, string|null>  $files
     */
    public function reply(Model|int|string $record, ?string $body, array $files = []): WorkRequestMessage
    {
        $body = trim((string) $body);

        if ($body === '' && $files === []) {
            throw MessageEmptyException::make();
        }

        return $this->transactions->run(function () use ($record, $body, $files): WorkRequestMessage {
            /** @var WorkRequest $request */
            $request = $this->lockForUpdate($record);

            if (! $request->isOpen()) {
                throw InvalidTransitionException::make();
            }

            $message = $this->storeMessage($request, WorkRequestMessage::KIND_TEXT, $body, $files);

            $this->recordActivity($request, 'message_posted', array_filter([
                'mesaj' => $body !== '' ? Str::limit(Str::squish($body), 120) : null,
                'dosya' => $files !== [] ? count($files) : null,
            ], fn ($value): bool => $value !== null));

            $this->notify($request, $this->participants($request), 'message_posted');

            return $message;
        });
    }

    /**
     * Talebi baska bir kisiye ya da birime yonlendirir (B32). Eski muhatap
     * yazismada kalir; gerekce zorunludur. Kabul edilmis talep yeniden
     * "acik"a doner. Yonlendirme yazismaya sistem satiri olarak, hareket
     * gecmisine "yonlendirildi" olarak dusulur.
     */
    public function forward(Model|int|string $record, string $targetKind, ?int $personnelId, ?int $orgUnitId, ?string $reason): WorkRequest
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw NoteRequiredException::make();
        }

        $kind = RequestTargetKind::tryFrom($targetKind) ?? throw TargetRequiredException::make();

        return $this->transactions->run(function () use ($record, $kind, $personnelId, $orgUnitId, $reason): WorkRequest {
            /** @var WorkRequest $request */
            $request = $this->lockForUpdate($record);

            if (! $request->isOpen()) {
                throw InvalidTransitionException::make();
            }

            $personnelId = $kind === RequestTargetKind::Personnel ? (int) $personnelId : null;
            $orgUnitId = $kind === RequestTargetKind::OrgUnit ? (int) $orgUnitId : null;

            if (($personnelId ?? $orgUnitId ?? 0) <= 0) {
                throw TargetRequiredException::make();
            }

            $sameTarget = $kind === $request->target_kind
                && $personnelId === ($request->target_personnel_id !== null ? (int) $request->target_personnel_id : null)
                && $orgUnitId === ($request->target_org_unit_id !== null ? (int) $request->target_org_unit_id : null);

            if ($sameTarget
                || $personnelId === (int) $request->requester_personnel_id
                || ($personnelId !== null && $personnelId === (int) $request->approver_personnel_id)) {
                throw ForwardNotAllowedException::make();
            }

            $previous = $request->targetLabel();

            $request->fill([
                'target_kind' => $kind->value,
                'target_personnel_id' => $personnelId,
                'target_org_unit_id' => $orgUnitId,
                'assignee_personnel_id' => $personnelId,
                'status' => WorkRequestStatus::Open->value,
                'accepted_at' => null,
            ])->save();
            $request->load($this->with);

            $this->storeMessage($request, WorkRequestMessage::KIND_FORWARD, sprintf('%s → %s%s%s', $previous, $request->targetLabel(), "\n", $reason), []);

            $this->recordActivity($request, 'forwarded', [
                'kime' => ['onceki' => $previous, 'yeni' => $request->targetLabel()],
                'gerekce' => Str::limit(Str::squish($reason), 200),
            ]);

            $this->notify($request, $this->targetRecipients($request), 'forwarded');
            $this->notify($request, $this->requesterRecipients($request), 'forwarded_info');

            return $request;
        });
    }

    /**
     * Form verisinden ek dosyalari ayirir: gecici anahtar => orijinal ad.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function pullAttachments(array &$data): array
    {
        $paths = (array) ($data['attachments'] ?? []);
        $names = (array) ($data['attachment_names'] ?? []);
        unset($data['attachments'], $data['attachment_names']);

        if (! SchemaReadiness::hasBatch('B32')) {
            return [];
        }

        $files = [];

        foreach ($paths as $key => $path) {
            $files[(string) $path] = $names[$key] ?? basename((string) $path);
        }

        return $files;
    }

    /**
     * Yazisma taraflari: talep eden (ve adina konustugu birimin yoneticisi),
     * muhatap kisi ya da sorumlu, onay mercii. Birime gelen ve sorumlusu
     * olmayan talepte birim uyeleri de dahildir.
     *
     * @return \Illuminate\Support\Collection<int, Personnel>
     */
    private function participants(WorkRequest $request): \Illuminate\Support\Collection
    {
        $people = $this->requesterRecipients($request);

        if ($request->assignee instanceof Personnel) {
            $people->push($request->assignee);
        } else {
            $people = $people->merge($this->targetRecipients($request));
        }

        if ($request->approver instanceof Personnel) {
            $people->push($request->approver);
        }

        return $people->unique(fn (Personnel $personnel) => $personnel->getKey())->values();
    }

    /**
     * @param  array<string, string|null>  $files
     */
    private function storeMessage(WorkRequest $request, string $kind, string $body, array $files): WorkRequestMessage
    {
        $message = new WorkRequestMessage([
            'work_request_id' => $request->getKey(),
            'author_personnel_id' => $this->actorId(),
            'message_kind' => $kind,
            'body' => $body !== '' ? Str::limit($body, 10000, '') : null,
        ]);
        $message->save();

        $order = 0;

        foreach ($files as $tempKey => $originalName) {
            $file = $this->fileObjects->createFromUpload((string) $tempKey, is_string($originalName) ? $originalName : null);

            $message->files()->create(['file_object_id' => $file->getKey(), 'sort_order' => $order++]);
        }

        return $message;
    }

    /**
     * Onay motoru sonucu (WorkRequestSubject): hareket kaydi ve taraflara
     * bildirim. Elle onaya gonderilen talepte durum degismez; onay, muhatabin
     * isi yapmasi icin yetki verir. Onaya tabi talepte (D-87) "onay bekliyor"
     * durumundaki talep onaylaninca kapanir, reddedilir / kapanirsa muhataba
     * geri doner.
     */
    public function noteApproval(?WorkRequest $request, string $event, int $approvalRequestId, ?string $comment = null, ?int $deciderPersonnelId = null): void
    {
        if ($request === null) {
            return;
        }

        $this->transactions->run(function () use ($request, $event, $approvalRequestId, $comment, $deciderPersonnelId): void {
            $this->recordActivity($request, $event, array_filter([
                'talep_no' => $request->request_no,
                'onay_talebi' => $approvalRequestId,
                'yorum' => $comment,
            ], fn ($value): bool => $value !== null && $value !== ''));

            $notifyEvent = in_array($event, ['approval_approved', 'approval_rejected'], true) ? $event : null;

            if (SchemaReadiness::hasBatch('B11D')) {
                /** @var WorkRequest $locked */
                $locked = $this->lockForUpdate($request);

                if ($locked->status === WorkRequestStatus::AwaitingApproval && (int) $locked->approval_request_id === $approvalRequestId) {
                    if ($event === 'approval_approved') {
                        $now = Carbon::now('UTC');
                        $locked->fill([
                            'status' => WorkRequestStatus::Done->value,
                            'closed_at' => $now,
                            'closed_by_personnel_id' => $deciderPersonnelId ?? $this->actor->personnelId(),
                        ])->save();

                        $this->recordActivity($locked, 'completed', [
                            'durum' => ['onceki' => WorkRequestStatus::AwaitingApproval->getLabel(), 'yeni' => WorkRequestStatus::Done->getLabel()],
                            'talep_no' => $locked->request_no,
                        ]);
                    } elseif (in_array($event, ['approval_rejected', 'approval_closed'], true)) {
                        $locked->fill([
                            'status' => WorkRequestStatus::InProgress->value,
                            'completed_at' => null,
                            'approval_request_id' => null,
                        ])->save();

                        $this->recordActivity($locked, 'approval_returned', array_filter([
                            'durum' => ['onceki' => WorkRequestStatus::AwaitingApproval->getLabel(), 'yeni' => WorkRequestStatus::InProgress->getLabel()],
                            'talep_no' => $locked->request_no,
                            'gerekce' => $comment,
                        ], fn ($value): bool => $value !== null && $value !== ''));

                        $notifyEvent = 'approval_returned';
                    }

                    $request = $locked;
                }
            }

            if ($notifyEvent !== null) {
                $request->load($this->with);
                $this->notify($request, $this->requesterRecipients($request)->merge($this->targetRecipients($request)), $notifyEvent);
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

        // Onaya tabi talep (D-87): B11D uygulanmadan bu alanlar yazilmaz.
        unset($data['approval_request_id']);

        if (! SchemaReadiness::hasBatch('B11D')) {
            unset($data['requires_approval'], $data['approver_personnel_id']);
        } else {
            if (array_key_exists('requires_approval', $data)) {
                $data['requires_approval'] = (bool) $data['requires_approval'];

                if (! $data['requires_approval']) {
                    $data['approver_personnel_id'] = null;
                }
            }

            if (array_key_exists('approver_personnel_id', $data)) {
                $data['approver_personnel_id'] = filled($data['approver_personnel_id']) ? (int) $data['approver_personnel_id'] : null;
            }
        }

        return $data;
    }

    /** Talep eden kendi talebini kabul edemez / tamamlayamaz / reddedemez. */
    private function assertNotRequester(WorkRequest $request): void
    {
        if ((int) $request->requester_personnel_id === $this->actorId()) {
            throw RequesterCannotHandleException::make();
        }
    }

    /**
     * Onaya tabi talepte onay mercii zorunludur; talep eden ya da muhatap kisi olamaz.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertApprover(array $data, int $requesterId): void
    {
        if (! SchemaReadiness::hasBatch('B11D') || ! (bool) ($data['requires_approval'] ?? false)) {
            return;
        }

        $approverId = (int) ($data['approver_personnel_id'] ?? 0);

        if ($approverId <= 0) {
            throw ApproverRequiredException::make();
        }

        if ($approverId === $requesterId || $approverId === (int) ($data['target_personnel_id'] ?? 0)) {
            throw ApproverInvalidException::make();
        }
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
                'awaiting_approval', 'approver_designated' => Heroicon::OutlinedCheckBadge,
                'approval_returned' => Heroicon::OutlinedArrowUturnLeft,
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
