<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\HandoffStatus;
use App\Enums\Acquisition\HandoffVersionStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\ReviewDecision;
use App\Enums\Project\ProjectOrigin;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\DuplicateRecordException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\OperationHandoff;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Project\Project;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Acquisition\HandoffItemService;
use App\Services\Acquisition\HandoffReviewService;
use App\Services\Acquisition\OperationHandoffService;
use App\Services\Acquisition\OperationHandoffVersionService;
use App\Services\Acquisition\ProposalService;
use App\Services\Acquisition\ProposalVersionService;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\TransactionRunner;

/**
 * Tekliften projeye dogrudan donusum (D-68).
 *
 * Kanonik zinciri (teklif -> devir -> kabul -> PRJ) atlamaz, tek
 * transaction'da kendisi yurutur: teklif secilir, surum gerekirse onaylanir,
 * business case `won` asamasina yurutulur, Operasyona devir kaydi ve surumu
 * acilir, kontrol listesi tamamlanir (sozlesme yoksa CONTRACT maddesi D-10
 * istisnasiyla muaf), surum gonderilir ve Proje Grubu kabulu yazilir;
 * kabul transaction'i PRJ kodunu ve projeyi (origin = converted) uretir.
 */
final class ProjectConversionService
{
    /** Girdi dizisinden projeye kopyalanan alanlar. */
    private const OVERRIDE_KEYS = [
        'name', 'project_manager_employee_id', 'site_location', 'planned_start_on', 'planned_finish_on',
        'description', 'site_address_line1', 'site_address_line2', 'site_district', 'site_city',
        'site_postal_code', 'site_country_code', 'site_latitude', 'site_longitude', 'site_note',
    ];

    public function __construct(
        private readonly TransactionRunner $transactions,
        private readonly ActivityRecorder $activities,
        private readonly BusinessCaseService $businessCases,
        private readonly ProposalService $proposals,
        private readonly ProposalVersionService $proposalVersions,
        private readonly OperationHandoffService $handoffs,
        private readonly OperationHandoffVersionService $handoffVersions,
        private readonly HandoffItemService $handoffItems,
        private readonly HandoffReviewService $reviews,
    ) {}

    /**
     * @param  array<string, mixed>  $data  proposal_version_id?, approve_draft (bool), comment, name, project_manager_employee_id, planned_start_on, planned_finish_on, adres alanlari
     */
    public function convertProposal(Proposal|int|string $proposal, array $data = []): Project
    {
        return $this->transactions->run(function () use ($proposal, $data): Project {
            $proposalId = $proposal instanceof Proposal ? $proposal->getKey() : $proposal;

            /** @var Proposal $proposal */
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposalId);

            /** @var BusinessCase $case */
            $case = BusinessCase::query()->lockForUpdate()->findOrFail($proposal->business_case_id);

            if (Project::query()->where('business_case_id', $case->getKey())->exists()) {
                throw DuplicateRecordException::make();
            }

            if (in_array($case->acquisition_stage, [AcquisitionStage::Lost, AcquisitionStage::Cancelled], true)) {
                throw GuardNotSatisfiedException::make(['reason' => 'kaybedilmis veya iptal edilmis is projeye donusturulemez']);
            }

            if (! $proposal->is_selected) {
                $this->proposals->select($proposal);
            }

            $version = $this->resolveVersion($proposal, $data['proposal_version_id'] ?? null);
            $version = $this->ensureApproved($version, (bool) ($data['approve_draft'] ?? false));

            $this->advanceCaseTo($case, AcquisitionStage::Won);

            $handoff = OperationHandoff::query()->where('business_case_id', $case->getKey())->first();

            if ($handoff === null) {
                /** @var OperationHandoff $handoff */
                $handoff = $this->handoffs->create(['business_case_id' => $case->getKey()]);
            }

            if ($handoff->status === HandoffStatus::Accepted) {
                throw DuplicateRecordException::make();
            }

            $handoffVersion = OperationHandoffVersion::query()
                ->where('operation_handoff_id', $handoff->getKey())
                ->where('status', HandoffVersionStatus::Submitted->value)
                ->orderByDesc('version_no')
                ->first();

            if ($handoffVersion === null) {
                $handoffVersion = $this->prepareHandoffVersion($handoff, $version);
            }

            $overrides = array_intersect_key($data, array_flip(self::OVERRIDE_KEYS));
            $overrides['origin'] = ProjectOrigin::Converted;

            $this->reviews->create([
                'handoff_version_id' => $handoffVersion->getKey(),
                'decision' => ReviewDecision::Accepted,
                'comment' => filled($data['comment'] ?? null) ? $data['comment'] : 'Tekliften projeye dogrudan donusum',
                'project_overrides' => $overrides,
            ]);

            /** @var Project $project */
            $project = Project::query()->where('business_case_id', $case->getKey())->firstOrFail();

            $this->activities->record(new ActivityInput(
                subjectType: 'project',
                subjectId: (int) $project->getKey(),
                actionCode: 'project.converted',
                changes: ['proposal_id' => $proposal->getKey(), 'proposal_version_id' => $version->getKey(), 'business_case_id' => $case->getKey()],
            ));

            return $project->refresh();
        });
    }

    private function resolveVersion(Proposal $proposal, int|string|null $requested): ProposalVersion
    {
        $query = ProposalVersion::query()->where('proposal_id', $proposal->getKey());

        $version = $requested !== null && $requested !== ''
            ? $query->whereKey((int) $requested)->first()
            : $query->whereNotIn('status', [ProposalVersionStatus::Superseded->value, ProposalVersionStatus::Withdrawn->value])
                ->orderByDesc('version_no')
                ->first();

        if ($version === null) {
            throw GuardNotSatisfiedException::make(['reason' => 'teklifin donusturulecek bir surumu yok']);
        }

        if (in_array($version->status, [ProposalVersionStatus::Superseded, ProposalVersionStatus::Withdrawn], true)) {
            throw GuardNotSatisfiedException::make(['reason' => 'gecersiz (superseded/withdrawn) surum donusturulemez']);
        }

        return $version;
    }

    /** Taslak/incelemedeki surumu (izin verildiyse) onaylar. */
    private function ensureApproved(ProposalVersion $version, bool $approveDraft): ProposalVersion
    {
        if (in_array($version->status, [ProposalVersionStatus::Approved, ProposalVersionStatus::Submitted], true)) {
            return $version;
        }

        if (! $approveDraft) {
            throw GuardNotSatisfiedException::make(['reason' => 'teklif surumu onayli degil; donusumde "surumu onayla" secilmeli']);
        }

        if ($version->status === ProposalVersionStatus::Draft) {
            $version = $this->proposalVersions->changeStatus($version, ProposalVersionStatus::Review);
        }

        return $this->proposalVersions->changeStatus($version, ProposalVersionStatus::Approved);
    }

    /** Business case'i izinli gecislerle hedef asamaya yurutur (en kisa yol). */
    private function advanceCaseTo(BusinessCase $case, AcquisitionStage $target): void
    {
        $case->refresh();

        if ($case->acquisition_stage === $target) {
            return;
        }

        $path = $this->shortestPath($case->acquisition_stage, $target);

        if ($path === null) {
            throw GuardNotSatisfiedException::make(['reason' => "is dosyasi {$case->acquisition_stage->getLabel()} asamasindan {$target->getLabel()} asamasina yurutulemiyor"]);
        }

        foreach ($path as $stage) {
            $this->businessCases->changeStage($case, $stage, 'converted_to_project');
            $case->refresh();
        }
    }

    /**
     * @return list<AcquisitionStage>|null
     */
    private function shortestPath(AcquisitionStage $from, AcquisitionStage $to): ?array
    {
        $queue = [[$from, []]];
        $seen = [$from->value => true];

        while ($queue !== []) {
            [$stage, $path] = array_shift($queue);

            foreach ($stage->allowedTargets() as $next) {
                if (in_array($next, [AcquisitionStage::Lost, AcquisitionStage::Cancelled], true)) {
                    continue;
                }

                $nextPath = [...$path, $next];

                if ($next === $to) {
                    return $nextPath;
                }

                if (! isset($seen[$next->value])) {
                    $seen[$next->value] = true;
                    $queue[] = [$next, $nextPath];
                }
            }
        }

        return null;
    }

    /** Yeni devir surumu acar, kontrol listesini tamamlar ve gonderir. */
    private function prepareHandoffVersion(OperationHandoff $handoff, ProposalVersion $version): OperationHandoffVersion
    {
        /** @var OperationHandoffVersion $handoffVersion */
        $handoffVersion = $this->handoffVersions->create([
            'operation_handoff_id' => $handoff->getKey(),
            'proposal_version_id' => $version->getKey(),
            'assumptions' => ['Tekliften projeye dogrudan donusum; sozlesme/LOI/NTP daha sonra baglanabilir.'],
        ]);

        foreach ($handoffVersion->items()->get() as $item) {
            if ($item->completion_state === CompletionState::Complete) {
                continue;
            }

            $state = ($item->item_code === 'CONTRACT' && $handoffVersion->contract_version_id === null)
                ? CompletionState::Waived
                : CompletionState::Complete;

            $this->handoffItems->update($item, ['completion_state' => $state->value]);
        }

        return $this->handoffVersions->submit($handoffVersion);
    }
}
