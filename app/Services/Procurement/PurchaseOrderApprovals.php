<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Approval\ApprovalPolicyStatus;
use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Approval\PolicyVersionStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Approval\ApprovalPolicy;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalStep;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Services\Approval\ApproverResolver;
use App\Services\Approval\Subjects\PurchaseOrderSubject;
use App\Services\Audit\ActorContext;

/**
 * Siparis onayi kurali (D-192; D-189'un siparis duzeyindeki hali). Kullanici
 * 10 Ekim 2026: "bazi tutarlara kadar onay yetkisi Satin alma ekibinde, daha
 * buyuk tutarlar Yonetim Kurulu Baskaninda; bu onay olmadan siparis
 * tamamlandiya cekilemez" ve "4000 TL'ye kadar onaylayabilir".
 *
 * - Siparisin butun kalemleri katalogda "Onay gerekmez" ise siparis onaysizdir.
 * - Aksi halde siparisin TL toplami konu turu purchase_order olan onay
 *   politikalarindan birine duser; merciler politika adimlarindan cozulur.
 * - Onay saglandi: siparis Onaylandi ya da sonraki asamalarda.
 * - Tek adimli politikada onayci siparisi acan kisinin kendisiyse (Satin alma
 *   mudurunun kendi yetkisi) onay talebi acilmadan onaylanabilir.
 */
final class PurchaseOrderApprovals
{
    public function __construct(
        private readonly ApproverResolver $resolver,
        private readonly PurchaseOrderSubject $subject,
        private readonly ActorContext $actor,
    ) {}

    public function state(PurchaseOrder $order): PurchaseApprovalState
    {
        if (! $this->requiresApproval($order)) {
            return PurchaseApprovalState::notRequired();
        }

        $status = $order->statusEnum();
        $pending = $status === PurchaseOrderStatus::AwaitingApproval && $this->openRequest($order);
        $satisfied = $status->isApprovedOrLater();
        $amount = $order->totalInTry();

        if ($amount === null) {
            return new PurchaseApprovalState(true, $satisfied, $pending, null, [], null, PurchaseApprovalState::MISSING_RATE);
        }

        $version = $this->policyFor($amount);

        if (! $version instanceof ApprovalPolicyVersion) {
            return new PurchaseApprovalState(true, $satisfied, $pending, $amount, [], null, PurchaseApprovalState::MISSING_POLICY);
        }

        [$names, $ids, $stepCount] = $this->approvers($version, $order);
        $actorId = $this->actor->personnelId();
        $self = $stepCount === 1 && $actorId !== null && in_array($actorId, $ids, true);

        return new PurchaseApprovalState(true, $satisfied, $pending, $amount, $names, $version->policy?->localizedName() ?? (string) $version->policy?->getAttribute('name_tr'), null, $self);
    }

    /** Kalemlerden biri onay gerektiriyor mu (katalog kalemi yoksa gerektirir)? */
    public function requiresApproval(PurchaseOrder $order): bool
    {
        $lines = $order->lines()->with('catalogItem')->get();

        if ($lines->isEmpty()) {
            return true;
        }

        return $lines->contains(static function (PurchaseOrderLine $line): bool {
            $item = $line->catalogItem;

            return ! $item instanceof CatalogItem || (bool) $item->getAttribute('requires_approval');
        });
    }

    /** Tutarin dustugu yayinlanmis politika surumu (konu: purchase_order). */
    public function policyFor(float $amount): ?ApprovalPolicyVersion
    {
        $policies = ApprovalPolicy::query()
            ->with('currentVersion.policy')
            ->where('subject_type', PurchaseOrderSubject::TYPE)
            ->where('status', ApprovalPolicyStatus::Active->value)
            ->whereNotNull('current_version_id')
            ->orderBy('code')
            ->get();

        foreach ($policies as $policy) {
            $version = $policy->currentVersion;

            if ($version instanceof ApprovalPolicyVersion && $version->status === PolicyVersionStatus::Published && $version->coversAmount($amount)) {
                return $version;
            }
        }

        return null;
    }

    private function openRequest(PurchaseOrder $order): bool
    {
        $request = $order->approvalRequest;

        return $request instanceof ApprovalRequest
            && in_array($request->getAttribute('status'), [ApprovalRequestStatus::Pending, ApprovalRequestStatus::InProgress], true);
    }

    /**
     * Politika adimlarinin onaycilari: "Ad Soyad (Gorev)", kimlikler ve adim sayisi.
     *
     * @return array{0: list<string>, 1: list<int>, 2: int}
     */
    private function approvers(ApprovalPolicyVersion $version, PurchaseOrder $order): array
    {
        $context = $this->subject->resolve((int) $order->getKey(), null);
        $requesterId = $this->actor->personnelId() ?? (int) ($order->getAttribute('created_by_personnel_id') ?? 0);
        $names = [];
        $ids = [];
        $steps = $version->steps()->get();

        foreach ($steps as $step) {
            /** @var ApprovalStep $step */
            if ($context === null) {
                break;
            }

            $resolved = $this->resolver->resolve($step, $context, $requesterId);
            $stepName = $step->localizedName();

            if ($resolved->personnelIds === []) {
                $names[] = $stepName;

                continue;
            }

            foreach (Personnel::query()->whereIn('id', $resolved->personnelIds)->orderBy('full_name')->get(['id', 'full_name']) as $person) {
                $ids[] = (int) $person->getKey();
                $names[] = $person->getAttribute('full_name').' ('.$stepName.')';
            }
        }

        return [array_values(array_unique($names)), array_values(array_unique($ids)), $steps->count()];
    }
}
