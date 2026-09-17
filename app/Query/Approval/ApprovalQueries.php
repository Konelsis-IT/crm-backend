<?php

declare(strict_types=1);

namespace App\Query\Approval;

use App\Enums\Approval\ApprovalPolicyStatus;
use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Approval\DelegationStatus;
use App\Enums\Approval\RequestStepStatus;
use App\Models\Approval\ApprovalPolicy;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalRequestStep;
use App\Models\Approval\ApprovalStep;
use App\Models\Approval\Delegation;
use App\Models\Authorization\Role;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Onay ekranlarinin okuma sorgulari: onay kutusu sayaci, sekme suzgecleri,
 * politika/rol secenekleri, karar dugmesi gorunurlugu, bir konunun acik talebi.
 */
final class ApprovalQueries
{
    /** Personelin karar bekleyen adim sayisi (menu rozeti). */
    public function inboxCount(int $personnelId): int
    {
        if (! SchemaReadiness::hasBatch('B07')) {
            return 0;
        }

        return ApprovalRequestStep::query()
            ->where('personnel_id', $personnelId)
            ->where('status', RequestStepStatus::Active->value)
            ->whereHas('request', fn (Builder $query) => $query->where('status', ApprovalRequestStatus::InProgress->value))
            ->count();
    }

    /** Listeyi "bana gelenler"e daraltir. */
    public function applyInbox(Builder $query, int $personnelId): Builder
    {
        return $query
            ->where('status', ApprovalRequestStatus::InProgress->value)
            ->whereHas('steps', fn (Builder $steps) => $steps
                ->where('personnel_id', $personnelId)
                ->where('status', RequestStepStatus::Active->value));
    }

    /** Listeyi "benim taleplerim"e daraltir. */
    public function applyMine(Builder $query, int $personnelId): Builder
    {
        return $query->where('personnel_id', $personnelId);
    }

    /** Listeyi acik taleplere daraltir. */
    public function applyOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ApprovalRequestStatus::Pending->value, ApprovalRequestStatus::InProgress->value]);
    }

    /**
     * Karar dugmeleri bu personele gosterilsin mi: aktif adimda kendi satiri
     * ya da gecerli vekaletle temsil ettigi kisinin satiri var mi? Maker-checker
     * acikken talep sahibi hicbir zaman karar veremez.
     */
    public function canDecide(ApprovalRequest $request, int $personnelId): bool
    {
        if ($request->status !== ApprovalRequestStatus::InProgress) {
            return false;
        }

        $version = $request->policyVersion;

        if ($version !== null && $version->requires_maker_checker && (int) $request->personnel_id === $personnelId) {
            return false;
        }

        $active = $request->steps()
            ->where('status', RequestStepStatus::Active->value)
            ->with('step')
            ->get();

        if ($active->contains(fn (ApprovalRequestStep $row): bool => (int) $row->personnel_id === $personnelId)) {
            return true;
        }

        $now = Carbon::now('UTC');
        $policyId = (int) ($version?->approval_policy_id ?? 0);

        $grantors = Delegation::query()
            ->where('delegate_personnel_id', $personnelId)
            ->where('status', DelegationStatus::Active->value)
            ->whereIn('capability_code', [Delegation::CAPABILITY_APPROVAL_DECIDE, 'approval.*'])
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>', $now)
            ->get()
            ->filter(fn (Delegation $delegation): bool => $delegation->coversPolicy($policyId))
            ->map(fn (Delegation $delegation): int => (int) $delegation->grantor_personnel_id)
            ->all();

        if ($grantors === []) {
            return false;
        }

        return $active->contains(fn (ApprovalRequestStep $row): bool => in_array((int) $row->personnel_id, $grantors, true)
            && (bool) ($row->step?->allows_delegation ?? false));
    }

    /** Koda gore aktif (yayimli surumu olan) politika kimligi; yoksa null. */
    public function policyIdByCode(string $code): ?int
    {
        if (! SchemaReadiness::hasBatch('B07')) {
            return null;
        }

        $id = ApprovalPolicy::query()
            ->where('code', $code)
            ->where('status', ApprovalPolicyStatus::Active->value)
            ->whereNotNull('current_version_id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Konu turu icin yayimli politikalar (secim listesi).
     *
     * @return array<int, string>
     */
    public function publishedPolicyOptions(string $subjectType): array
    {
        if (! SchemaReadiness::hasBatch('B07')) {
            return [];
        }

        $options = [];

        $policies = ApprovalPolicy::query()
            ->where('subject_type', $subjectType)
            ->where('status', ApprovalPolicyStatus::Active->value)
            ->whereNotNull('current_version_id')
            ->orderBy('code')
            ->get();

        foreach ($policies as $policy) {
            $options[(int) $policy->getKey()] = $policy->code.' · '.$policy->localizedName();
        }

        return $options;
    }

    /**
     * Tum politikalar (vekalet kapsami secimi).
     *
     * @return array<int, string>
     */
    public function allPolicyOptions(): array
    {
        if (! SchemaReadiness::hasBatch('B07')) {
            return [];
        }

        $options = [];

        foreach (ApprovalPolicy::query()->orderBy('code')->get() as $policy) {
            $options[(int) $policy->getKey()] = $policy->code.' · '.$policy->localizedName();
        }

        return $options;
    }

    /**
     * RBAC rol adlari (adim onayci secimi).
     *
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        return Role::query()->orderBy('name')->pluck('name', 'name')->all();
    }

    /**
     * Bir konunun (orn. revizyon) acik talebi, varsa.
     */
    public function openRequestFor(string $subjectType, int $subjectId): ?ApprovalRequest
    {
        if (! SchemaReadiness::hasBatch('B07')) {
            return null;
        }

        /** @var ApprovalRequest|null $request */
        $request = ApprovalRequest::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereIn('status', [ApprovalRequestStatus::Pending->value, ApprovalRequestStatus::InProgress->value])
            ->latest('requested_at')
            ->first();

        return $request;
    }

    /**
     * Surumdeki adim kodlari (sira, kod, ad) — politika ozetinde.
     *
     * @return list<string>
     */
    public function stepSummary(int $versionId): array
    {
        return ApprovalStep::query()
            ->where('approval_policy_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get()
            ->map(fn (ApprovalStep $step): string => $step->sequence_no.'. '.$step->localizedName())
            ->all();
    }
}
