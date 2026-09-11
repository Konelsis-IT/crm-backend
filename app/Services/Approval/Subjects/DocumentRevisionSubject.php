<?php

declare(strict_types=1);

namespace App\Services\Approval\Subjects;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Approval\ApprovalRequest;
use App\Models\Document\DocumentRevision;
use App\Services\Document\DocumentRevisionService;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Dokuman revizyonu onay konusu (08 SS1.4 `approval_request_id`): hash
 * revizyonun icerik hash'i + baslik + amac + dil'den uretilir; onaylaninca
 * revizyon "onaylandi", reddedilince taslaga doner ve bir inceleme kaydi
 * (document_reviews) acilir.
 *
 * DocumentRevisionService bu sinifa da bagimli oldugu icin (talep aciliminda
 * revizyonu incelemeye alma) servis kurucuda degil, kullanildigi anda cozulur.
 */
final class DocumentRevisionSubject implements ApprovalSubject
{
    public const TYPE = DocumentRevision::APPROVAL_SUBJECT_TYPE;

    public function __construct(private readonly Container $container) {}

    public function subjectType(): string
    {
        return self::TYPE;
    }

    public function resolve(int $subjectId, ?int $subjectRevisionId): ?SubjectContext
    {
        /** @var DocumentRevision|null $revision */
        $revision = DocumentRevision::query()->with('document')->find($subjectId);

        if ($revision === null || $revision->document === null) {
            return null;
        }

        $document = $revision->document;

        $url = null;

        try {
            $url = DocumentResource::getUrl('view', ['record' => $document]);
        } catch (Throwable) {
            // Konsol/planlanmis is baglaminda panel rotasi uretilemeyebilir.
        }

        return new SubjectContext(
            hash: hash('sha256', implode('|', [
                (string) $revision->content_hash,
                (string) $revision->title,
                $revision->purpose->value,
                (string) $revision->language,
            ])),
            label: sprintf('%s · %s — Rev %s', $document->document_no, $document->title, $revision->revision_code),
            url: $url,
            ownerPersonnelId: $document->owner_personnel_id !== null ? (int) $document->owner_personnel_id : null,
            orgUnitId: $document->owner_org_unit_id !== null ? (int) $document->owner_org_unit_id : null,
            projectId: $document->project_id !== null ? (int) $document->project_id : null,
        );
    }

    public function onRequested(ApprovalRequest $request): void
    {
        $revision = $this->revision($request);

        if ($revision !== null) {
            $this->revisions()->attachApprovalRequest($revision, (int) $request->getKey());
        }
    }

    public function onApproved(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $revision = $this->revision($request);

        if ($revision !== null) {
            $this->revisions()->applyApprovalOutcome($revision, true, $deciderPersonnelId, (int) $request->getKey(), $comment);
        }
    }

    public function onRejected(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $revision = $this->revision($request);

        if ($revision !== null) {
            $this->revisions()->applyApprovalOutcome($revision, false, $deciderPersonnelId, (int) $request->getKey(), $comment);
        }
    }

    public function onClosed(ApprovalRequest $request): void
    {
        // Revizyon "incelemede" kalir; talep sahibi yeni talep acar ya da taslaga ceker.
    }

    private function revision(ApprovalRequest $request): ?DocumentRevision
    {
        /** @var DocumentRevision|null $revision */
        $revision = DocumentRevision::query()->find((int) $request->subject_id);

        return $revision;
    }

    private function revisions(): DocumentRevisionService
    {
        return $this->container->make(DocumentRevisionService::class);
    }
}
