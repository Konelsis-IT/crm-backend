<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Approval\ApprovalPolicyStatus;
use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Approval\PolicyVersionStatus;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Models\Approval\ApprovalPolicy;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalStep;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Services\Approval\ApproverResolver;
use App\Services\Approval\Subjects\PurchaseNeedSubject;
use App\Services\Audit\ActorContext;

/**
 * Siparis onayi kurali (D-189, kullanici 10 Ekim 2026): "Bazi urunlerde bazi
 * tutarlara kadar onay yetkisi Satin alma ekibinde, daha buyuk tutarlar
 * Yonetim Kurulu Baskaninda; bu onay olmadan siparis tamamlandiya cekilemez.
 * Tamamlandiya cekmeye calisildiginda ekranda uyari penceresi ile onaylatmaniz
 * gereken merciler gorulecek." Sinir 4.000 TL (kullanici cevabi).
 *
 * - Katalog kaleminde "Onay gerekir" kapaliysa siparis onaysizdir.
 * - Aksi halde onerilen teklifin TL tutari onay politikalarindan birine
 *   duser (konu turu purchase_need, tutar araligi; Ayarlar > Onay
 *   Politikalari'ndan degistirilir). Merciler politika adimlarindan cozulur.
 * - Onay saglandi: kalem Onaylandi / Siparis verildi / Tamamlandi durumunda ve
 *   onerilen teklif onaydan sonra degismemis.
 * - Tek adimli politikada onayci siparisi acan kisinin kendisiyse (Satin alma
 *   mudurunun kendi yetkisi) onay talebi acilmadan onaylanabilir.
 */
final class PurchaseApprovals
{
    public function __construct(
        private readonly ApproverResolver $resolver,
        private readonly PurchaseNeedSubject $subject,
        private readonly ActorContext $actor,
    ) {}

    public function state(PurchaseNeed $need): PurchaseApprovalState
    {
        $item = $need->catalogItem;

        if ($item instanceof CatalogItem && ! (bool) $item->getAttribute('requires_approval')) {
            return PurchaseApprovalState::notRequired();
        }

        $status = $need->statusEnum();
        $pending = $status === PurchaseNeedStatus::AwaitingApproval && $this->openRequest($need);
        $satisfied = in_array($status, [PurchaseNeedStatus::Approved, PurchaseNeedStatus::Ordered, PurchaseNeedStatus::Completed], true);
        $quote = $need->recommendedQuote;

        if (! $quote instanceof PurchaseQuote) {
            return new PurchaseApprovalState(true, $satisfied, $pending, null, [], null, PurchaseApprovalState::MISSING_QUOTE);
        }

        $amount = $quote->totalInTry();

        if ($amount === null) {
            return new PurchaseApprovalState(true, $satisfied, $pending, null, [], null, PurchaseApprovalState::MISSING_RATE);
        }

        $version = $this->policyFor($amount);

        if (! $version instanceof ApprovalPolicyVersion) {
            return new PurchaseApprovalState(true, $satisfied, $pending, $amount, [], null, PurchaseApprovalState::MISSING_POLICY);
        }

        [$names, $ids, $stepCount] = $this->approvers($version, $need);
        $actorId = $this->actor->personnelId();
        $self = $stepCount === 1 && $actorId !== null && in_array($actorId, $ids, true);

        return new PurchaseApprovalState(true, $satisfied, $pending, $amount, $names, $version->policy?->localizedName() ?? (string) $version->policy?->getAttribute('name_tr'), null, $self);
    }

    /** Tutarin dustugu yayinlanmis politika surumu (konu: purchase_need). */
    public function policyFor(float $amount): ?ApprovalPolicyVersion
    {
        $policies = ApprovalPolicy::query()
            ->with('currentVersion.policy')
            ->where('subject_type', PurchaseNeedSubject::TYPE)
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

    private function openRequest(PurchaseNeed $need): bool
    {
        $request = $need->approvalRequest;

        return $request instanceof ApprovalRequest
            && in_array($request->getAttribute('status'), [ApprovalRequestStatus::Pending, ApprovalRequestStatus::InProgress], true);
    }

    /**
     * Politika adimlarinin onaycilari: "Ad Soyad (Gorev)", kimlikler ve adim sayisi.
     *
     * @return array{0: list<string>, 1: list<int>, 2: int}
     */
    private function approvers(ApprovalPolicyVersion $version, PurchaseNeed $need): array
    {
        $context = $this->subject->resolve((int) $need->getKey(), null);
        $requesterId = $this->actor->personnelId() ?? (int) ($need->getAttribute('requested_by_personnel_id') ?? 0);
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
