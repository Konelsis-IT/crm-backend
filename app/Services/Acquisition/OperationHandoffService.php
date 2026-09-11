<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\BusinessCodeStatus;
use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\HandoffStatus;
use App\Enums\Acquisition\HandoffVersionStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Exceptions\Acquisition\HandoffNotAcceptableException;
use App\Exceptions\DuplicateRecordException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCode;
use App\Models\Acquisition\OperationHandoff;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Project\Project;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\ProjectOpener;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Operasyona devir koku servisi (10 SS5, 14 SS2.24 SM-OH).
 *
 * create: business case basina tek devir; case 'won' ise
 * handover_preparing asamasina tasinir. accept: kabul transaction'i —
 * sabit kilit sirasiyla business case -> handoff -> surum; PRJ-n kodu
 * (ayni sequence_no, ikinci uretim uk_business_codes_case_kind ile
 * reddedilir), proje + workstream + gate instance'lari (ProjectOpener),
 * business case operation segmentine gecer. Herhangi bir adim
 * basarisizsa hepsi geri alinir.
 */
final class OperationHandoffService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['businessCase', 'preparer', 'acceptedVersion', 'acceptor'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly BusinessCaseService $businessCases,
        private readonly ProjectOpener $opener,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var BusinessCase $case */
            $case = BusinessCase::query()->lockForUpdate()->findOrFail((int) ($data['business_case_id'] ?? 0));

            if (OperationHandoff::query()->where('business_case_id', $case->getKey())->exists()) {
                throw DuplicateRecordException::make();
            }

            /** @var OperationHandoff $handoff */
            $handoff = parent::create([
                'business_case_id' => $case->getKey(),
                'prepared_by_employee_id' => $data['prepared_by_employee_id'] ?? $this->actor->personnelId() ?? $case->proposal_owner_employee_id ?? $case->owner_employee_id,
                'status' => HandoffStatus::Preparing,
            ]);

            if ($case->acquisition_stage->canTransitionTo(AcquisitionStage::HandoverPreparing)) {
                $this->businessCases->changeStage($case, AcquisitionStage::HandoverPreparing);
            }

            return $handoff;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['business_case_id'], $data['status'], $data['accepted_version_id'], $data['accepted_by_personnel_id'], $data['accepted_at']);

        return parent::update($record, $data);
    }

    /** Kabul disindaki SM-OH gecisleri (in_review/rejected/cancelled/preparing). */
    public function changeStatus(Model|int|string $record, HandoffStatus $target, ?string $reason = null): OperationHandoff
    {
        if ($target === HandoffStatus::Accepted) {
            throw InvalidTransitionException::make(['from' => '-', 'to' => $target->getLabel()]);
        }

        return $this->transactions->run(function () use ($record, $target, $reason): OperationHandoff {
            /** @var OperationHandoff $handoff */
            $handoff = $this->lockForUpdate($record);
            $from = $handoff->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $handoff->forceFill(['status' => $target])->save();
            $this->recordActivity($handoff, 'status_changed', ['durum' => ['onceki' => $from->value, 'yeni' => $target->value], 'gerekce' => $reason]);

            $case = $handoff->businessCase;
            $stage = match ($target) {
                HandoffStatus::InReview => AcquisitionStage::HandoverReview,
                HandoffStatus::Preparing, HandoffStatus::Rejected => AcquisitionStage::HandoverPreparing,
                HandoffStatus::Cancelled => AcquisitionStage::Cancelled,
                default => null,
            };

            if ($stage !== null && $case->acquisition_stage !== $stage && $case->acquisition_stage->canTransitionTo($stage)) {
                $this->businessCases->changeStage($case, $stage, $reason);
            }

            return $handoff;
        });
    }

    /**
     * Kabul transaction'i. Basari halinde acilan projeyi doner.
     *
     * @param  array<string, mixed>  $projectOverrides  ProjectOpener::open() secenekleri
     */
    public function accept(Model|int|string $record, ?int $versionId = null, array $projectOverrides = []): Project
    {
        return $this->transactions->run(function () use ($record, $versionId, $projectOverrides): Project {
            /** @var OperationHandoff $handoff */
            $handoff = $this->lockForUpdate($record);

            /** @var BusinessCase $case */
            $case = BusinessCase::query()->lockForUpdate()->findOrFail($handoff->business_case_id);

            if ($handoff->status !== HandoffStatus::InReview) {
                throw HandoffNotAcceptableException::make(['reason' => 'devir incelemede degil']);
            }

            $versionQuery = OperationHandoffVersion::query()->lockForUpdate()->where('operation_handoff_id', $handoff->getKey());
            /** @var OperationHandoffVersion|null $version */
            $version = $versionId !== null
                ? $versionQuery->whereKey($versionId)->first()
                : $versionQuery->where('status', HandoffVersionStatus::Submitted->value)->orderByDesc('version_no')->first();

            if ($version === null || $version->status !== HandoffVersionStatus::Submitted) {
                throw HandoffNotAcceptableException::make(['reason' => 'gonderilmis (submitted) bir devir surumu yok']);
            }

            $this->assertVersionAcceptable($version);

            if (BusinessCode::query()->where('business_case_id', $case->getKey())->where('code_kind', BusinessCodeKind::Project->value)->exists()) {
                throw HandoffNotAcceptableException::make(['reason' => 'bu business case icin PRJ kodu zaten uretilmis']);
            }

            $offerCode = $this->businessCases->offerCode($case);
            $now = Carbon::now('UTC');

            $projectCode = BusinessCode::query()->create([
                'business_case_id' => $case->getKey(),
                'sequence_no' => $case->sequence_no,
                'code_kind' => BusinessCodeKind::Project,
                'issued_at' => $now,
                'issued_by_personnel_id' => $this->actor->personnelId() ?? $handoff->prepared_by_employee_id,
                'predecessor_code_id' => $offerCode?->getKey(),
                'status' => BusinessCodeStatus::Active,
            ]);
            $projectCode->refresh();

            $offerCode?->forceFill(['status' => BusinessCodeStatus::Historical])->save();

            $project = $this->opener->open($case, $version, $projectCode, $projectOverrides);

            OperationHandoffVersion::query()
                ->where('operation_handoff_id', $handoff->getKey())
                ->whereKeyNot($version->getKey())
                ->where('status', HandoffVersionStatus::Submitted->value)
                ->update(['status' => HandoffVersionStatus::Superseded->value]);

            $version->forceFill(['status' => HandoffVersionStatus::Accepted])->save();
            $handoff->forceFill([
                'status' => HandoffStatus::Accepted,
                'accepted_version_id' => $version->getKey(),
                'accepted_by_personnel_id' => $this->actor->personnelId(),
                'accepted_at' => $now,
            ])->save();

            $this->businessCases->changeStage($case, AcquisitionStage::HandoverAccepted);

            $this->recordActivity($handoff, 'accepted', [
                'surum' => $version->version_no,
                'proje_kodu' => $projectCode->formatted_code,
                'project_id' => $project->getKey(),
            ]);

            return $project;
        });
    }

    private function assertVersionAcceptable(OperationHandoffVersion $version): void
    {
        $proposalVersion = $version->proposalVersion;

        if ($proposalVersion === null || ! in_array($proposalVersion->status, [ProposalVersionStatus::Approved, ProposalVersionStatus::Submitted], true)) {
            throw HandoffNotAcceptableException::make(['reason' => 'devre bagli teklif surumu onayli degil']);
        }

        $pending = $version->items()
            ->whereNotIn('completion_state', [CompletionState::Complete->value, CompletionState::Waived->value, CompletionState::NotApplicable->value])
            ->count();

        if ($pending > 0) {
            throw HandoffNotAcceptableException::make(['reason' => "{$pending} devir maddesi tamamlanmamis"]);
        }

        // D-10: sozlesme/LOI/NTP zorunlu; yalniz gerekceli waiver ile bos kalabilir.
        if ($version->contract_version_id === null) {
            $waived = $version->items()
                ->where('item_code', 'CONTRACT')
                ->where('completion_state', CompletionState::Waived->value)
                ->exists();

            if (! $waived) {
                throw HandoffNotAcceptableException::make(['reason' => 'sozlesme/LOI/NTP surumu bagli degil (D-10); istisna icin CONTRACT maddesi gerekceli muaf tutulmali']);
            }
        }
    }
}
