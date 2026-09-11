<?php

declare(strict_types=1);

namespace App\Services\Approval\Subjects;

use App\Models\Approval\ApprovalRequest;

/**
 * Onay motoruna baglanan her konu turu (dokuman revizyonu, teklif surumu,
 * ...) bu sozlesmeyi uygular. Motor konunun tablosunu bilmez; hash, etiket
 * ve sonucun konuya nasil islenecegi buradan gelir.
 */
interface ApprovalSubject
{
    /** 12 SS2.4 `subject_type` kodu (registry). */
    public function subjectType(): string;

    /** Konu bulunamazsa null. */
    public function resolve(int $subjectId, ?int $subjectRevisionId): ?SubjectContext;

    /** Talep acildiginda (konu incelemeye alinir, talep baglanir). */
    public function onRequested(ApprovalRequest $request): void;

    public function onApproved(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void;

    public function onRejected(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void;

    /** Iptal / sure asimi / gecersizlesme: konu serbest kalir. */
    public function onClosed(ApprovalRequest $request): void;
}
