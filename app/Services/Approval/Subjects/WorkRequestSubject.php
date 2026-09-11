<?php

declare(strict_types=1);

namespace App\Services\Approval\Subjects;

use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Models\Approval\ApprovalRequest;
use App\Models\WorkRequest\WorkRequest;
use App\Services\WorkRequest\WorkRequestService;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Talep onay konusu (D-84 eki, 11 Eylul 2026): bir talep onaya gonderilebilir;
 * Onaylar ekranindan da bir talep icin onay talebi acilabilir. Hash: baslik,
 * aciklama, muhatap, oncelik ve son tarihten uretilir (talep degisirse onay
 * gecersizlesir). Sonuc talebe hareket kaydi + talep edene bildirim olarak islenir.
 */
final class WorkRequestSubject implements ApprovalSubject
{
    public const TYPE = 'work_request';

    public function __construct(private readonly Container $container) {}

    public function subjectType(): string
    {
        return self::TYPE;
    }

    public function resolve(int $subjectId, ?int $subjectRevisionId): ?SubjectContext
    {
        /** @var WorkRequest|null $request */
        $request = WorkRequest::query()
            ->with(['targetPersonnel', 'targetOrgUnit', 'requester'])
            ->find($subjectId);

        if ($request === null) {
            return null;
        }

        $url = null;

        try {
            $url = WorkRequestResource::getUrl('view', ['record' => $request]);
        } catch (Throwable) {
            // Konsol baglaminda panel rotasi uretilemeyebilir.
        }

        $ownerId = $request->targetsOrgUnit()
            ? ($request->targetOrgUnit?->manager_personnel_id ?? $request->assignee_personnel_id)
            : $request->target_personnel_id;

        return new SubjectContext(
            hash: hash('sha256', implode('|', [
                (string) $request->title,
                (string) $request->description,
                $request->target_kind->value,
                (string) $request->target_personnel_id,
                (string) $request->target_org_unit_id,
                $request->priority->value,
                $request->due_on?->toDateString() ?? '',
            ])),
            label: sprintf('%s · %s', $request->request_no, $request->title),
            url: $url,
            ownerPersonnelId: $ownerId !== null ? (int) $ownerId : null,
            orgUnitId: $request->target_org_unit_id !== null ? (int) $request->target_org_unit_id : ($request->requester_org_unit_id !== null ? (int) $request->requester_org_unit_id : null),
            projectId: $request->project_id !== null ? (int) $request->project_id : null,
        );
    }

    public function onRequested(ApprovalRequest $request): void
    {
        $this->requests()->noteApproval($this->workRequest($request), 'approval_requested', (int) $request->getKey());
    }

    public function onApproved(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $this->requests()->noteApproval($this->workRequest($request), 'approval_approved', (int) $request->getKey(), $comment);
    }

    public function onRejected(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $this->requests()->noteApproval($this->workRequest($request), 'approval_rejected', (int) $request->getKey(), $comment);
    }

    public function onClosed(ApprovalRequest $request): void
    {
        $this->requests()->noteApproval($this->workRequest($request), 'approval_closed', (int) $request->getKey());
    }

    private function workRequest(ApprovalRequest $request): ?WorkRequest
    {
        /** @var WorkRequest|null $workRequest */
        $workRequest = WorkRequest::query()->find((int) $request->subject_id);

        return $workRequest;
    }

    private function requests(): WorkRequestService
    {
        return $this->container->make(WorkRequestService::class);
    }
}
