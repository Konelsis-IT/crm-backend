<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\ApprovalDecisionKind;
use App\Enums\Approval\ApprovalMode;
use App\Enums\Approval\ApprovalPolicyStatus;
use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Approval\DecisionChannel;
use App\Enums\Approval\DecisionRule;
use App\Enums\Approval\DelegationStatus;
use App\Enums\Approval\InvalidationReason;
use App\Enums\Approval\PolicyVersionStatus;
use App\Enums\Approval\RequestStepStatus;
use App\Enums\Approval\ResolutionStatus;
use App\Enums\Approval\UnresolvedReason;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\Approval\ActiveRequestExistsException;
use App\Exceptions\Approval\ApproverUnresolvedException;
use App\Exceptions\Approval\CommentRequiredException;
use App\Exceptions\Approval\NoPublishedPolicyException;
use App\Exceptions\Approval\NotAnActiveApproverException;
use App\Exceptions\Approval\PolicyHasNoStepsException;
use App\Exceptions\Approval\RequestNotDecidableException;
use App\Exceptions\Approval\SubjectChangedException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Approval\ApprovalPolicy;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalRequestStep;
use App\Models\Approval\ApprovalStep;
use App\Models\Approval\Delegation;
use App\Services\AbstractService;
use App\Services\Approval\Subjects\ApprovalSubjectRegistry;
use App\Services\Approval\Subjects\SubjectContext;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Onay motoru (12 SS2.4-2.7, SM-APR 14 SS2.38).
 *
 * request: konu icin yayimli politika surumu cozulur, acik talep varsa
 * reddedilir, adimlar onaycilariyla birlikte materialize edilir (zorunlu bir
 * adimin onaycisi bulunamazsa talep hic acilmaz), ilk adim(lar) aktiflenir,
 * onaycilar bildirilir.
 *
 * decide: yalniz aktif adimdaki onayci (ya da onun gecerli vekili) karar
 * verir; maker-checker acikken talep sahibi hicbir adimda karar veremez;
 * karar aninda konunun hash'i yeniden dogrulanir, degistiyse talep
 * gecersizlesir. Adim kurali (herhangi biri / hepsi / cogunluk) ve mod
 * (sirali / paralel / nisap) uygulanir; sonuc konuya islenir.
 *
 * cancel / invalidate / expireOverdue: acik talebi kapatir; sure asiminda
 * escalation zinciri bildirilir.
 */
final class ApprovalRequestService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['policyVersion.policy', 'requester', 'steps.step', 'steps.approver'];

    protected string $orderBy = 'requested_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly ApprovalSubjectRegistry $subjects,
        private readonly ApproverResolver $resolver,
        private readonly ApprovalNotifier $notifier,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** Konu icin acik (karar bekleyen) talep var mi? */
    public function hasOpenRequest(string $subjectType, int $subjectId, ?int $subjectRevisionId = null): bool
    {
        return ApprovalRequest::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('subject_revision_id', $subjectRevisionId)
            ->whereIn('status', [ApprovalRequestStatus::Pending->value, ApprovalRequestStatus::InProgress->value])
            ->exists();
    }

    public function request(string $subjectType, int $subjectId, ?int $subjectRevisionId = null, ?int $policyId = null, ?string $note = null): ApprovalRequest
    {
        return $this->transactions->run(function () use ($subjectType, $subjectId, $subjectRevisionId, $policyId, $note): ApprovalRequest {
            $requesterId = $this->actor->personnelId();

            if ($requesterId === null) {
                throw ActorRequiredException::make();
            }

            $subject = $this->subjects->for($subjectType);
            $context = $subject->resolve($subjectId, $subjectRevisionId);

            if ($context === null) {
                throw RecordNotFoundException::make();
            }

            if ($this->hasOpenRequest($subjectType, $subjectId, $subjectRevisionId)) {
                throw ActiveRequestExistsException::make();
            }

            $version = $this->resolvePolicyVersion($subjectType, $policyId, $context->amount);

            $previous = ApprovalRequest::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->count();

            /** @var ApprovalRequest $request */
            $request = parent::create([
                'approval_policy_version_id' => $version->getKey(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_revision_id' => $subjectRevisionId,
                'subject_hash' => $context->hash,
                'subject_label' => mb_substr($context->label, 0, 255),
                'amount' => $context->amount,
                'currency_code' => $context->currencyCode,
                'idempotency_key' => substr(hash('sha256', implode('|', [$subjectType, $subjectId, (string) $subjectRevisionId, $context->hash, $previous])), 0, 32),
                'personnel_id' => $requesterId,
                'note' => $note,
                'requested_at' => Carbon::now('UTC'),
                'current_step_sequence' => 0,
                'status' => ApprovalRequestStatus::Pending,
            ]);

            $this->materializeSteps($request, $version, $context, $requesterId);

            $subject->onRequested($request);
            $this->advance($request, null, null);

            return $this->reload($request);
        });
    }

    public function decide(Model|int|string $record, ApprovalDecisionKind $decision, ?string $comment = null): ApprovalRequest
    {
        $comment = filled($comment) ? trim((string) $comment) : null;

        if ($decision->requiresComment() && $comment === null) {
            throw CommentRequiredException::make();
        }

        $actorId = $this->actor->personnelId();

        if ($actorId === null) {
            throw ActorRequiredException::make();
        }

        $outcome = $this->transactions->run(function () use ($record, $decision, $comment, $actorId): array {
            /** @var ApprovalRequest $request */
            $request = $this->lockForUpdate($record);

            if ($request->status !== ApprovalRequestStatus::InProgress) {
                throw RequestNotDecidableException::make(['status' => $request->status->getLabel()]);
            }

            $version = $request->policyVersion;

            if ($version !== null && $version->requires_maker_checker && (int) $request->personnel_id === $actorId) {
                throw NotAnActiveApproverException::make();
            }

            [$row, $delegation] = $this->activeRowFor($request, $actorId);

            $context = $this->subjects->for($request->subject_type)->resolve((int) $request->subject_id, $request->subject_revision_id);

            if ($context === null || $context->hash !== $request->subject_hash) {
                $this->notifier->requestClosed($request, 'invalidated');
                $this->close($request, ApprovalRequestStatus::Invalidated, InvalidationReason::HashMismatch);

                return ['changed' => true, 'request' => $request];
            }

            $decisionRow = $row->decisions()->create([
                'personnel_id' => $actorId,
                'on_behalf_of_personnel_id' => $delegation !== null ? $row->personnel_id : null,
                'decision' => $decision,
                'comment' => $comment,
                'approved_subject_hash' => $context->hash,
                'channel' => DecisionChannel::Ui,
                'decided_at' => Carbon::now('UTC'),
            ]);

            if ($delegation !== null) {
                $decisionRow->delegationSnapshot()->create([
                    'source_delegation_id' => $delegation->getKey(),
                    'grantor_personnel_id' => $delegation->grantor_personnel_id,
                    'delegate_personnel_id' => $delegation->delegate_personnel_id,
                    'scope_snapshot' => mb_substr($delegation->scopeLabel(), 0, 120),
                    'valid_from_snapshot' => $delegation->valid_from,
                    'valid_until_snapshot' => $delegation->valid_until,
                ]);
            }

            $row->forceFill([
                'status' => match ($decision) {
                    ApprovalDecisionKind::Approved => RequestStepStatus::Approved,
                    ApprovalDecisionKind::Rejected, ApprovalDecisionKind::Returned => RequestStepStatus::Rejected,
                    ApprovalDecisionKind::Abstained => RequestStepStatus::Skipped,
                },
                'decided_at' => Carbon::now('UTC'),
            ])->save();

            $this->recordActivity($request, 'decided', [
                'karar' => $decision->value,
                'adim' => $row->step?->step_code,
                'vekaleten' => $delegation !== null ? $row->personnel_id : null,
                'gerekce' => $comment,
            ]);

            /** @var ApprovalStep $step */
            $step = $row->step;
            $rows = $this->rowsForStep($request, (int) $step->getKey());
            $stepOutcome = $this->stepOutcome($rows, $step);

            if ($stepOutcome === 'approved' || $stepOutcome === 'rejected') {
                $this->skipActiveRows($rows);
            }

            if ($stepOutcome === 'rejected' && ! $step->is_optional) {
                $this->finalize($request, false, $actorId, $comment);
            } elseif ($stepOutcome !== 'pending') {
                $this->advance($request, $actorId, $comment);
            }

            return ['changed' => false, 'request' => $request];
        });

        if ($outcome['changed']) {
            throw SubjectChangedException::make();
        }

        return $this->reload($outcome['request']);
    }

    public function cancel(Model|int|string $record, ?string $reason = null): ApprovalRequest
    {
        return $this->transactions->run(function () use ($record, $reason): ApprovalRequest {
            /** @var ApprovalRequest $request */
            $request = $this->lockForUpdate($record);

            if (! $request->isOpen()) {
                throw RequestNotDecidableException::make(['status' => $request->status->getLabel()]);
            }

            $this->notifier->requestClosed($request, 'cancelled');
            $this->close($request, ApprovalRequestStatus::Cancelled, null, $reason);

            return $this->reload($request);
        });
    }

    public function invalidate(Model|int|string $record, InvalidationReason $reason): ApprovalRequest
    {
        return $this->transactions->run(function () use ($record, $reason): ApprovalRequest {
            /** @var ApprovalRequest $request */
            $request = $this->lockForUpdate($record);

            if (! $request->isOpen()) {
                throw RequestNotDecidableException::make(['status' => $request->status->getLabel()]);
            }

            $this->notifier->requestClosed($request, 'invalidated');
            $this->close($request, ApprovalRequestStatus::Invalidated, $reason);

            return $this->reload($request);
        });
    }

    /** Konusu degisen/geri cekilen kaydin acik taleplerini gecersizlestirir; sayisini doner. */
    public function invalidateOpenFor(string $subjectType, int $subjectId, InvalidationReason $reason): int
    {
        $ids = ApprovalRequest::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereIn('status', [ApprovalRequestStatus::Pending->value, ApprovalRequestStatus::InProgress->value])
            ->pluck('id');

        foreach ($ids as $id) {
            $this->invalidate((int) $id, $reason);
        }

        return $ids->count();
    }

    /** SLA'si gecen aktif adimlari ve taleplerini "suresi doldu" yapar; escalation bildirir. */
    public function expireOverdue(?Carbon $now = null): int
    {
        $now ??= Carbon::now('UTC');

        $requestIds = ApprovalRequestStep::query()
            ->where('status', RequestStepStatus::Active->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->distinct()
            ->pluck('approval_request_id');

        $expired = 0;

        foreach ($requestIds as $requestId) {
            $expired += $this->transactions->run(function () use ($requestId, $now): int {
                /** @var ApprovalRequest $request */
                $request = $this->lockForUpdate((int) $requestId);

                if (! $request->isOpen()) {
                    return 0;
                }

                $overdue = $request->steps()
                    ->where('status', RequestStepStatus::Active->value)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $now)
                    ->with(['approver', 'step'])
                    ->get();

                if ($overdue->isEmpty()) {
                    return 0;
                }

                foreach ($overdue as $row) {
                    $row->forceFill(['status' => RequestStepStatus::Expired, 'decided_at' => $now])->save();
                }

                $this->notifier->requestExpired($request, $overdue);
                $this->close($request, ApprovalRequestStatus::Expired);

                return 1;
            });
        }

        return $expired;
    }

    // ------------------------------------------------------------------ ic isler

    private function resolvePolicyVersion(string $subjectType, ?int $policyId, ?float $amount): ApprovalPolicyVersion
    {
        $policies = ApprovalPolicy::query()
            ->with('currentVersion')
            ->where('subject_type', $subjectType)
            ->where('status', ApprovalPolicyStatus::Active->value)
            ->whereNotNull('current_version_id')
            ->when($policyId !== null, fn ($query) => $query->whereKey($policyId))
            ->orderBy('code')
            ->get();

        foreach ($policies as $policy) {
            $version = $policy->currentVersion;

            if ($version === null || $version->status !== PolicyVersionStatus::Published) {
                continue;
            }

            if ($policyId !== null || $version->coversAmount($amount) || ($amount !== null && $version->applies_min_amount === null && $version->applies_max_amount === null)) {
                return $version;
            }
        }

        throw NoPublishedPolicyException::make(['subject' => __('approval_policy.subject_types.'.$subjectType)]);
    }

    private function materializeSteps(ApprovalRequest $request, ApprovalPolicyVersion $version, SubjectContext $context, int $requesterId): void
    {
        $steps = $version->steps()->get();

        if ($steps->isEmpty()) {
            throw PolicyHasNoStepsException::make();
        }

        foreach ($steps as $step) {
            $resolved = $this->resolver->resolve($step, $context, $requesterId);
            $ids = $resolved->personnelIds;
            $reason = $resolved->reason;

            if ($version->requires_maker_checker) {
                $filtered = array_values(array_diff($ids, [$requesterId]));

                if ($ids !== [] && $filtered === []) {
                    $reason = UnresolvedReason::SodConflict;
                }

                $ids = $filtered;
            }

            if ($ids === []) {
                $reason ??= UnresolvedReason::NoRoleHolder;

                if (! $step->is_optional) {
                    throw ApproverUnresolvedException::make(['step' => $step->localizedName(), 'reason' => $reason->getLabel()]);
                }

                $request->steps()->create([
                    'approval_step_id' => $step->getKey(),
                    'sequence_no' => $step->sequence_no,
                    'personnel_id' => null,
                    'resolved_role_snapshot' => $step->resolver_type->value,
                    'resolution_status' => ResolutionStatus::Unresolved,
                    'unresolved_reason' => $reason,
                    'status' => RequestStepStatus::Skipped,
                ]);

                continue;
            }

            foreach ($ids as $personnelId) {
                $request->steps()->create([
                    'approval_step_id' => $step->getKey(),
                    'sequence_no' => $step->sequence_no,
                    'personnel_id' => $personnelId,
                    'resolved_role_snapshot' => $step->resolver_type->value,
                    'resolution_status' => ResolutionStatus::Resolved,
                    'status' => RequestStepStatus::Waiting,
                ]);
            }
        }
    }

    /**
     * Siradaki adimi aktifler ya da talep tamamlandiysa sonuclandirir.
     */
    private function advance(ApprovalRequest $request, ?int $deciderId, ?string $comment): void
    {
        $request->refresh();

        if (! $request->isOpen()) {
            return;
        }

        /** @var ApprovalPolicyVersion $version */
        $version = $request->policyVersion;
        $rows = $request->steps()->with('step')->get();

        if ($version->mode === ApprovalMode::Sequential) {
            $pending = $rows->filter(fn (ApprovalRequestStep $row): bool => ! $row->status->isFinal());

            if ($pending->isEmpty()) {
                $this->finalize($request, true, $deciderId, $comment);

                return;
            }

            $sequence = (int) $pending->min('sequence_no');
            $toActivate = $rows->filter(fn (ApprovalRequestStep $row): bool => (int) $row->sequence_no === $sequence && $row->status === RequestStepStatus::Waiting);

            if ($toActivate->isNotEmpty()) {
                $this->activate($request, $toActivate, $sequence);
            }

            return;
        }

        $waiting = $rows->filter(fn (ApprovalRequestStep $row): bool => $row->status === RequestStepStatus::Waiting);

        if ($waiting->isNotEmpty()) {
            $this->activate($request, $waiting, (int) $rows->min('sequence_no'));
            $rows = $request->steps()->with('step')->get();
        }

        $outcomes = [];

        foreach ($rows->groupBy('approval_step_id') as $stepId => $stepRows) {
            /** @var ApprovalStep|null $step */
            $step = $stepRows->first()?->step;
            $outcomes[(int) $stepId] = $step === null ? 'skipped' : $this->stepOutcome($stepRows, $step);
        }

        $approvedCount = count(array_filter($outcomes, fn (string $outcome): bool => $outcome === 'approved'));
        $allDone = ! in_array('pending', $outcomes, true);

        if ($version->mode === ApprovalMode::Quorum) {
            $quorum = max(1, (int) ($version->quorum_count ?? 1));

            if ($approvedCount >= $quorum) {
                $this->skipActiveRows($rows);
                $this->finalize($request, true, $deciderId, $comment);

                return;
            }

            if ($allDone) {
                $this->finalize($request, false, $deciderId, $comment);
            }

            return;
        }

        if ($allDone) {
            $this->finalize($request, $approvedCount > 0 || $outcomes === [], $deciderId, $comment);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApprovalRequestStep>  $rows
     */
    private function activate(ApprovalRequest $request, $rows, int $sequence): void
    {
        $now = Carbon::now('UTC');
        /** @var ApprovalPolicyVersion $version */
        $version = $request->policyVersion;

        foreach ($rows as $row) {
            $sla = $row->step?->sla_minutes ?? $version->sla_minutes;

            $row->forceFill([
                'status' => RequestStepStatus::Active,
                'activated_at' => $now,
                'due_at' => $sla !== null ? $now->copy()->addMinutes((int) $sla) : null,
            ])->save();

            $this->notifier->stepActivated($row->fresh(['approver', 'request', 'step']) ?? $row);
        }

        $request->forceFill([
            'status' => ApprovalRequestStatus::InProgress,
            'current_step_sequence' => $sequence,
        ])->save();

        $this->recordActivity($request, 'step_activated', ['adim_sirasi' => $sequence]);
    }

    private function finalize(ApprovalRequest $request, bool $approved, ?int $deciderId, ?string $comment): void
    {
        $status = $approved ? ApprovalRequestStatus::Approved : ApprovalRequestStatus::Rejected;

        $request->forceFill([
            'status' => $status,
            'decided_at' => Carbon::now('UTC'),
        ])->save();

        $this->recordActivity($request, $approved ? 'approved' : 'rejected', ['konu' => $request->subject_label]);

        $subject = $this->subjects->for($request->subject_type);

        if ($approved) {
            $subject->onApproved($request, $deciderId, $comment);
        } else {
            $subject->onRejected($request, $deciderId, $comment);
        }

        $this->notifier->requestDecided($request, $approved);
    }

    private function close(ApprovalRequest $request, ApprovalRequestStatus $status, ?InvalidationReason $reason = null, ?string $note = null): void
    {
        foreach ($request->steps()->where('status', RequestStepStatus::Active->value)->get() as $row) {
            if ($status !== ApprovalRequestStatus::Expired) {
                $row->forceFill(['status' => RequestStepStatus::Skipped])->save();
            }
        }

        $request->forceFill([
            'status' => $status,
            'decided_at' => Carbon::now('UTC'),
            'invalidation_reason' => $reason,
        ])->save();

        $this->recordActivity($request, $status->value, array_filter([
            'konu' => $request->subject_label,
            'neden' => $reason?->value,
            'gerekce' => $note,
        ], fn ($value): bool => $value !== null));

        $this->subjects->for($request->subject_type)->onClosed($request);
    }

    /**
     * Aktorun aktif adim satiri; yoksa gecerli bir vekaletle temsil ettigi
     * kisinin satiri. Bulunamazsa NotAnActiveApproverException.
     *
     * @return array{0: ApprovalRequestStep, 1: Delegation|null}
     */
    private function activeRowFor(ApprovalRequest $request, int $actorId): array
    {
        /** @var ApprovalRequestStep|null $own */
        $own = $request->steps()
            ->where('status', RequestStepStatus::Active->value)
            ->where('personnel_id', $actorId)
            ->with('step')
            ->first();

        if ($own !== null) {
            return [$own, null];
        }

        $now = Carbon::now('UTC');
        $policyId = (int) ($request->policyVersion?->approval_policy_id ?? 0);

        $delegations = Delegation::query()
            ->where('delegate_personnel_id', $actorId)
            ->where('status', DelegationStatus::Active->value)
            ->whereIn('capability_code', [Delegation::CAPABILITY_APPROVAL_DECIDE, 'approval.*'])
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>', $now)
            ->get()
            ->filter(fn (Delegation $delegation): bool => $delegation->coversPolicy($policyId));

        foreach ($delegations as $delegation) {
            /** @var ApprovalRequestStep|null $row */
            $row = $request->steps()
                ->where('status', RequestStepStatus::Active->value)
                ->where('personnel_id', $delegation->grantor_personnel_id)
                ->with('step')
                ->first();

            if ($row !== null && ($row->step?->allows_delegation ?? false)) {
                return [$row, $delegation];
            }
        }

        throw NotAnActiveApproverException::make();
    }

    /**
     * @return Collection<int, ApprovalRequestStep>
     */
    private function rowsForStep(ApprovalRequest $request, int $stepId): Collection
    {
        return $request->steps()->where('approval_step_id', $stepId)->with('step')->get();
    }

    /**
     * Adimin durumu: approved | rejected | pending | skipped.
     *
     * @param  \Illuminate\Support\Collection<int, ApprovalRequestStep>  $rows
     */
    private function stepOutcome($rows, ApprovalStep $step): string
    {
        $resolved = $rows->filter(fn (ApprovalRequestStep $row): bool => $row->resolution_status === ResolutionStatus::Resolved);

        if ($resolved->isEmpty()) {
            return 'skipped';
        }

        $approved = $resolved->where('status', RequestStepStatus::Approved)->count();
        $rejected = $resolved->where('status', RequestStepStatus::Rejected)->count();
        $expired = $resolved->where('status', RequestStepStatus::Expired)->count();
        $open = $resolved->filter(fn (ApprovalRequestStep $row): bool => ! $row->status->isFinal())->count();
        $total = $resolved->count();

        if ($expired > 0 && $open === 0 && $approved === 0) {
            return 'rejected';
        }

        return match ($step->decision_rule) {
            DecisionRule::AnyOne => $approved > 0 ? 'approved' : ($rejected > 0 ? 'rejected' : ($open > 0 ? 'pending' : 'rejected')),
            DecisionRule::All => $rejected > 0 ? 'rejected' : ($open > 0 ? 'pending' : ($approved > 0 ? 'approved' : 'rejected')),
            DecisionRule::Majority => $approved * 2 > $total ? 'approved' : ($rejected * 2 >= $total ? 'rejected' : ($open > 0 ? 'pending' : ($approved > $rejected ? 'approved' : 'rejected'))),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApprovalRequestStep>  $rows
     */
    private function skipActiveRows($rows): void
    {
        foreach ($rows as $row) {
            if ($row->status === RequestStepStatus::Active || $row->status === RequestStepStatus::Waiting) {
                $row->forceFill(['status' => RequestStepStatus::Skipped])->save();
            }
        }
    }

    private function reload(ApprovalRequest $request): ApprovalRequest
    {
        /** @var ApprovalRequest $fresh */
        $fresh = $this->query()->whereKey($request->getKey())->firstOrFail();

        return $fresh;
    }
}
