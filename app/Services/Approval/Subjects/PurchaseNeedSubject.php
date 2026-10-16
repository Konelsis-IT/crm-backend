<?php

declare(strict_types=1);

namespace App\Services\Approval\Subjects;

use App\Models\Approval\ApprovalRequest;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Services\Procurement\PurchaseNeedService;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Satin alma siparisi onay konusu (D-189). Tutar onerilen tedarikci teklifinin
 * TL karsiligidir; politika tutar araligina gore secilir (4.000 TL'ye kadar
 * Satin alma muduru, ustu Yonetim kurulu baskani). Hash: onerilen teklif,
 * tutar, kur ve miktar; teklif degisirse onay gecersizlesir. Sonuc kalemin
 * durumuna islenir (PurchaseNeedService::applyApproval).
 */
final class PurchaseNeedSubject implements ApprovalSubject
{
    public const TYPE = 'purchase_need';

    public function __construct(private readonly Container $container) {}

    public function subjectType(): string
    {
        return self::TYPE;
    }

    public function resolve(int $subjectId, ?int $subjectRevisionId): ?SubjectContext
    {
        /** @var PurchaseNeed|null $need */
        $need = PurchaseNeed::query()->with(['recommendedQuote', 'proposal', 'catalogItem'])->find($subjectId);

        if ($need === null) {
            return null;
        }

        $quote = $need->recommendedQuote;
        $amount = $quote instanceof PurchaseQuote ? $quote->totalInTry() : null;
        $url = null;

        try {
            $url = \App\Filament\Resources\PurchaseNeeds\PurchaseNeedResource::getUrl('view', ['record' => $need]);
        } catch (Throwable) {
            // Konsol baglaminda ya da ekran henuz yokken panel rotasi uretilemeyebilir.
        }

        $proposalNo = $need->proposal?->getAttribute('proposal_no');

        return new SubjectContext(
            hash: hash('sha256', implode('|', [
                (string) $need->getAttribute('recommended_quote_id'),
                $quote instanceof PurchaseQuote ? (string) $quote->getAttribute('total_amount') : '',
                $quote instanceof PurchaseQuote ? (string) $quote->getAttribute('currency_code') : '',
                $quote instanceof PurchaseQuote ? (string) $quote->getAttribute('try_rate') : '',
                (string) $need->getAttribute('quantity'),
            ])),
            label: trim(sprintf('%s%s', $need->getAttribute('title'), filled($proposalNo) ? ' · '.$proposalNo : '')),
            url: $url,
            ownerPersonnelId: $need->getAttribute('requested_by_personnel_id') !== null ? (int) $need->getAttribute('requested_by_personnel_id') : null,
            orgUnitId: $need->getAttribute('org_unit_id') !== null ? (int) $need->getAttribute('org_unit_id') : null,
            projectId: $need->getAttribute('project_id') !== null ? (int) $need->getAttribute('project_id') : null,
            amount: $amount,
            currencyCode: 'TRY',
        );
    }

    public function onRequested(ApprovalRequest $request): void
    {
        // Kalem durumu onaya gonderilirken PurchaseNeedService icinde yazilir.
    }

    public function onApproved(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $this->needs()->applyApproval((int) $request->subject_id, 'approved', $comment);
    }

    public function onRejected(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $this->needs()->applyApproval((int) $request->subject_id, 'rejected', $comment);
    }

    public function onClosed(ApprovalRequest $request): void
    {
        $this->needs()->applyApproval((int) $request->subject_id, 'closed', null);
    }

    private function needs(): PurchaseNeedService
    {
        return $this->container->make(PurchaseNeedService::class);
    }
}
