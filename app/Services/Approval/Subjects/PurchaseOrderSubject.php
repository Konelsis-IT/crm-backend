<?php

declare(strict_types=1);

namespace App\Services\Approval\Subjects;

use App\Models\Approval\ApprovalRequest;
use App\Models\Procurement\PurchaseOrder;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Satin alma siparisi onay konusu (D-192, B53). D-189'da onay siparis
 * kalemindeydi (PurchaseNeedSubject); kalemler artik secili teklifle birlikte
 * tek siparise donustugu icin onay siparisin TL toplamina bakar: 4.000 TL'ye
 * kadar Satin alma muduru, ustu Yonetim kurulu baskani (politikalar:
 * PurchaseOrderApprovalPolicy20261012Seeder; Ayarlar > Onay Politikalari).
 * Hash: toplam, para birimi, kur ve kalemler; siparis degisirse onay
 * gecersizlesir. Sonuc PurchaseOrderService::applyApproval'a islenir.
 */
final class PurchaseOrderSubject implements ApprovalSubject
{
    public const TYPE = 'purchase_order';

    public function __construct(private readonly Container $container) {}

    public function subjectType(): string
    {
        return self::TYPE;
    }

    public function resolve(int $subjectId, ?int $subjectRevisionId): ?SubjectContext
    {
        /** @var PurchaseOrder|null $order */
        $order = PurchaseOrder::query()->with(['lines', 'supplier'])->find($subjectId);

        if ($order === null) {
            return null;
        }

        $url = null;

        try {
            $url = \App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('view', ['record' => $order]);
        } catch (Throwable) {
            // Konsol baglaminda ya da ekran henuz yokken panel rotasi uretilemeyebilir.
        }

        $lines = $order->lines->map(static fn ($line): string => implode(':', [
            (string) $line->getKey(),
            (string) $line->getAttribute('quantity'),
            (string) $line->getAttribute('unit_price'),
            (string) $line->getAttribute('discount_rate'),
        ]))->implode(',');

        return new SubjectContext(
            hash: hash('sha256', implode('|', [
                (string) $order->getAttribute('total_amount'),
                (string) $order->getAttribute('currency_code'),
                (string) $order->getAttribute('try_rate'),
                $lines,
            ])),
            label: $order->supplierLabel() !== '' ? $order->getAttribute('order_no').' · '.$order->supplierLabel() : (string) $order->getAttribute('order_no'),
            url: $url,
            ownerPersonnelId: $order->getAttribute('created_by_personnel_id') !== null ? (int) $order->getAttribute('created_by_personnel_id') : null,
            orgUnitId: null,
            projectId: $order->getAttribute('project_id') !== null ? (int) $order->getAttribute('project_id') : null,
            amount: $order->totalInTry(),
            currencyCode: 'TRY',
        );
    }

    public function onRequested(ApprovalRequest $request): void
    {
        // Siparis durumu onaya gonderilirken PurchaseOrderService icinde yazilir.
    }

    public function onApproved(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $this->orders()->applyApproval((int) $request->subject_id, 'approved', $comment);
    }

    public function onRejected(ApprovalRequest $request, ?int $deciderPersonnelId, ?string $comment): void
    {
        $this->orders()->applyApproval((int) $request->subject_id, 'rejected', $comment);
    }

    public function onClosed(ApprovalRequest $request): void
    {
        $this->orders()->applyApproval((int) $request->subject_id, 'closed', null);
    }

    private function orders(): PurchaseOrderService
    {
        return $this->container->make(PurchaseOrderService::class);
    }
}
