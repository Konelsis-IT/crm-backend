<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Document\DocumentReviewDecision;
use App\Enums\Document\DocumentReviewType;
use App\Enums\Document\RevisionContentKind;
use App\Enums\Document\RevisionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\FileObject;
use App\Services\AbstractService;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Doküman revizyonu servisi.
 *
 * create override edilmistir: revision_no/revision_code otomatik uretilir;
 * icerik ya yuklenen dosyadir (FileObjectService, "original" rolunde
 * baglanir) ya da sistemde yazilan govdedir (body_html, D-75); hazirlayan
 * otomatik doldurulur.
 *
 * update yalniz taslak/incelemedeki revizyonlarda calisir: yayimlanmis/
 * onaylanmis bir revizyon uzerine yazilmaz (M04 cikis kriteri); degisiklik
 * icin yeni bir revizyon acilir. Govde degisince icerik hash'i yenilenir;
 * acik onay talebi varsa karar aninda hash uyusmazligi yakalanir (14 SS3/7).
 *
 * changeStatus: "issued" oldugunda dokumanin guncel revizyonu bu olur ve
 * oncekini "superseded" yapar. Onay motoru (B07) sonucu applyApprovalOutcome
 * ile revizyona islenir ve bir inceleme kaydi (document_reviews) acilir.
 */
final class DocumentRevisionService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['document', 'preparer', 'checker', 'approver', 'files.fileObject'];

    protected string $orderBy = 'revision_no';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly FileObjectService $fileObjects,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $documentId = (int) ($data['document_id'] ?? 0);
            $revisionNo = DocumentRevision::query()->where('document_id', $documentId)->max('revision_no') + 1;

            $tempPath = $data['file_temp_path'] ?? null;
            $originalName = $data['file_original_name'] ?? null;
            $body = is_string($data['body_html'] ?? null) && trim(strip_tags((string) $data['body_html'])) !== '' ? (string) $data['body_html'] : null;
            unset($data['file_temp_path'], $data['file_original_name'], $data['body_html'], $data['content_kind']);

            $fileObject = null;

            if (filled($tempPath)) {
                $fileObject = $this->fileObjects->createFromUpload((string) $tempPath, is_string($originalName) ? $originalName : null);
            }

            $kind = $fileObject === null && $body !== null ? RevisionContentKind::Authored : RevisionContentKind::Upload;

            $attributes = [
                ...$data,
                'document_id' => $documentId,
                'revision_no' => $revisionNo,
                'revision_code' => sprintf('%02d', $revisionNo),
                'content_hash' => $this->contentHash($fileObject, $body, (string) ($data['title'] ?? ''), $revisionNo),
                'prepared_by_personnel_id' => $this->actor->personnelId(),
                'prepared_at' => Carbon::now('UTC'),
            ];

            if (SchemaReadiness::hasBatch('B06A')) {
                $attributes['content_kind'] = $kind;
                $attributes['body_html'] = $kind === RevisionContentKind::Authored ? $body : null;
            }

            /** @var DocumentRevision $revision */
            $revision = parent::create($attributes);

            if ($fileObject !== null) {
                $revision->files()->create([
                    'file_object_id' => $fileObject->getKey(),
                    'file_role' => 'original',
                    'sort_order' => 0,
                ]);
            }

            return $revision;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var DocumentRevision $current */
        $current = $this->show($record);

        if (! $current->isEditable()) {
            throw InvalidTransitionException::make(['from' => $current->status->getLabel(), 'to' => '-']);
        }

        unset($data['file_temp_path'], $data['file_original_name'], $data['document_id'], $data['revision_no'], $data['revision_code'], $data['content_kind']);

        if (array_key_exists('body_html', $data)) {
            if (! SchemaReadiness::hasBatch('B06A') || $current->content_kind !== RevisionContentKind::Authored) {
                unset($data['body_html']);
            } elseif (is_string($data['body_html']) && $data['body_html'] !== (string) $current->body_html) {
                $data['content_hash'] = hash('sha256', $data['body_html']);
            }
        }

        return parent::update($current, $data);
    }

    /**
     * Revizyon durumunu degistirir. "issued" olduğunda dokumanin guncel
     * revizyonu bu olur, oncekini "superseded" yapar.
     */
    public function changeStatus(Model|int|string $record, RevisionStatus $target): DocumentRevision
    {
        return $this->transactions->run(function () use ($record, $target): DocumentRevision {
            /** @var DocumentRevision $revision */
            $revision = $this->lockForUpdate($record);
            $from = $revision->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make([
                    'from' => $from->getLabel(),
                    'to' => $target->getLabel(),
                ]);
            }

            $attributes = ['status' => $target];

            if ($target === RevisionStatus::Approved) {
                $attributes['approved_by_personnel_id'] = $this->actor->personnelId();
                $attributes['approved_at'] = Carbon::now('UTC');
            }

            if ($target === RevisionStatus::Issued) {
                $attributes['issued_at'] = Carbon::now('UTC');
            }

            $revision->forceFill($attributes)->save();

            $this->recordActivity($revision, 'status_changed', [
                'durum' => ['onceki' => $from->value, 'yeni' => $target->value],
            ]);

            if ($target === RevisionStatus::Issued) {
                $this->promoteToCurrentRevision($revision);
            }

            return $revision;
        });
    }

    /**
     * Orijinal dosyanin indirilmesini Personel Hareketleri'ne yazar (kontrollu
     * dokuman izi). Kendi transaction'ini acar; indirme ucu bunu cagirir.
     */
    public function recordDownload(DocumentRevision $revision, FileObject $file): void
    {
        $this->transactions->run(function () use ($revision, $file): void {
            $this->activities->record(new ActivityInput(
                subjectType: $this->subjectType(),
                subjectId: (int) $revision->getKey(),
                actionCode: $this->subjectType().'.downloaded',
                changes: ['dosya' => $file->original_name, 'document_id' => $revision->document_id],
            ));
        });
    }

    /**
     * Onay motoruna gonderilirken: taslak revizyon incelemeye alinir ve talep
     * baglanir. Cagiran (ApprovalRequestService) kendi transaction'i icindedir.
     */
    public function attachApprovalRequest(DocumentRevision $revision, int $approvalRequestId): DocumentRevision
    {
        return $this->transactions->run(function () use ($revision, $approvalRequestId): DocumentRevision {
            /** @var DocumentRevision $locked */
            $locked = $this->lockForUpdate($revision);

            if ($locked->status === RevisionStatus::Draft) {
                $locked->forceFill(['status' => RevisionStatus::InReview])->save();
                $this->recordActivity($locked, 'status_changed', [
                    'durum' => ['onceki' => RevisionStatus::Draft->value, 'yeni' => RevisionStatus::InReview->value],
                ]);
            }

            if (SchemaReadiness::hasBatch('B07')) {
                $locked->forceFill(['approval_request_id' => $approvalRequestId])->save();
            }

            return $locked;
        });
    }

    /**
     * Onay motorunun sonucu: onaylandiysa revizyon "onaylandi" olur (onaylayan
     * son karar veren), reddedildiyse taslaga doner; her iki durumda bir
     * inceleme kaydi (document_reviews, review_type = approve) acilir.
     */
    public function applyApprovalOutcome(DocumentRevision $revision, bool $approved, ?int $deciderPersonnelId, int $approvalRequestId, ?string $comment): DocumentRevision
    {
        return $this->transactions->run(function () use ($revision, $approved, $deciderPersonnelId, $approvalRequestId, $comment): DocumentRevision {
            /** @var DocumentRevision $locked */
            $locked = $this->lockForUpdate($revision);
            $from = $locked->status;
            $target = $approved ? RevisionStatus::Approved : RevisionStatus::Draft;

            if ($from !== $target && $from->canTransitionTo($target)) {
                $attributes = ['status' => $target];

                if ($approved) {
                    $attributes['approved_by_personnel_id'] = $deciderPersonnelId;
                    $attributes['approved_at'] = Carbon::now('UTC');
                }

                $locked->forceFill($attributes)->save();
                $this->recordActivity($locked, 'status_changed', [
                    'durum' => ['onceki' => $from->value, 'yeni' => $target->value],
                    'onay_talebi' => $approvalRequestId,
                ]);
            }

            if ($deciderPersonnelId !== null) {
                $review = [
                    'reviewer_personnel_id' => $deciderPersonnelId,
                    'review_type' => DocumentReviewType::Approve,
                    'decision' => $approved ? DocumentReviewDecision::Approved : DocumentReviewDecision::Rejected,
                    'comment' => $comment,
                    'decided_at' => Carbon::now('UTC'),
                ];

                if (SchemaReadiness::hasBatch('B07')) {
                    $review['approval_request_id'] = $approvalRequestId;
                }

                $locked->reviews()->create($review);
            }

            return $locked;
        });
    }

    private function contentHash(?FileObject $fileObject, ?string $body, string $title, int $revisionNo): string
    {
        if ($fileObject !== null) {
            return (string) $fileObject->sha256;
        }

        if ($body !== null) {
            return hash('sha256', $body);
        }

        return hash('sha256', $title.$revisionNo.now()->toIso8601String());
    }

    private function promoteToCurrentRevision(DocumentRevision $revision): void
    {
        /** @var Document $document */
        $document = Document::query()->lockForUpdate()->findOrFail($revision->document_id);

        $previousRevisionId = $document->current_revision_id;

        $document->forceFill(['current_revision_id' => $revision->getKey(), 'status' => 'active'])->save();

        if ($previousRevisionId !== null && $previousRevisionId !== $revision->getKey()) {
            DocumentRevision::query()->whereKey($previousRevisionId)->update([
                'status' => RevisionStatus::Superseded->value,
                'superseded_by_revision_id' => $revision->getKey(),
            ]);
        }
    }
}
