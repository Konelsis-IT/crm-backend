<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\ContractVersionStatus;
use App\Enums\Acquisition\HandoffItemType;
use App\Enums\Acquisition\HandoffStatus;
use App\Enums\Acquisition\HandoffVersionStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\ContractVersion;
use App\Models\Acquisition\HandoffItem;
use App\Models\Acquisition\OperationHandoff;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Acquisition\ProposalVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Operasyona devir surumu servisi (10 SS5.2-5.3).
 *
 * create: version_no otomatik; teklif surumu verilmezse secili teklifin
 * onayli/gonderilmis surumu, sozlesme surumu verilmezse business case'in
 * son yururlukteki sozlesme surumu baglanir; baseline_snapshot ve hash
 * uretilir; standart kontrol listesi maddeleri (BASELINE, SCHEDULE,
 * CONTRACT, RISKS, OPEN_ISSUES, DOC_MANIFEST) acilir.
 *
 * submit: butun maddeler complete/waived/not_applicable olmali; surum
 * submitted, devir in_review olur (SM-OH), onceki submitted surum superseded.
 */
final class OperationHandoffVersionService extends AbstractService
{
    protected string $model = OperationHandoffVersion::class;

    /** @var list<string> */
    protected array $with = ['handoff', 'proposalVersion', 'contractVersion', 'submitter'];

    protected string $orderBy = 'version_no';

    protected string $orderDirection = 'desc';

    /** @var array<string, array{0: HandoffItemType, 1: string}> */
    private const DEFAULT_ITEMS = [
        'BASELINE' => [HandoffItemType::Baseline, 'Fiyat ve marj baseline\'i teklif surumunden dogrulandi'],
        'SCHEDULE' => [HandoffItemType::Baseline, 'Taslak takvim ve kilometre taslari devredildi'],
        'CONTRACT' => [HandoffItemType::Document, 'Sozlesme/LOI/NTP surumu baglandi (D-10)'],
        'RISKS' => [HandoffItemType::Risk, 'Bilinen riskler ve varsayimlar listelendi'],
        'OPEN_ISSUES' => [HandoffItemType::OpenIssue, 'Acik konular ve musteri talepleri aktarildi'],
        'DOC_MANIFEST' => [HandoffItemType::Checklist, 'Doküman manifesti (teklif ekleri) tamamlandi'],
    ];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly OperationHandoffService $handoffs,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var OperationHandoff $handoff */
            $handoff = OperationHandoff::query()->lockForUpdate()->findOrFail((int) ($data['operation_handoff_id'] ?? 0));

            if (! in_array($handoff->status, [HandoffStatus::Preparing, HandoffStatus::Rejected], true)) {
                throw InvalidTransitionException::make(['from' => $handoff->status->getLabel(), 'to' => '-']);
            }

            $case = $handoff->businessCase;
            $proposalVersion = $this->resolveProposalVersion($case->getKey(), $data['proposal_version_id'] ?? null);
            $contractVersion = $this->resolveContractVersion($case->getKey(), $data['contract_version_id'] ?? null);

            $snapshot = [
                'business_case' => ['id' => $case->getKey(), 'sequence_no' => $case->sequence_no, 'title' => $case->title],
                'proposal_version' => [
                    'id' => $proposalVersion->getKey(),
                    'version_no' => $proposalVersion->version_no,
                    'currency_code' => $proposalVersion->currency_code,
                    'total_price' => $proposalVersion->total_price,
                    'margin_pct' => $proposalVersion->margin_pct,
                    'version_hash' => $proposalVersion->version_hash,
                ],
                'contract_version' => $contractVersion === null ? null : [
                    'id' => $contractVersion->getKey(),
                    'version_no' => $contractVersion->version_no,
                    'contract_value' => $contractVersion->contract_value,
                    'version_hash' => $contractVersion->version_hash,
                ],
                'assumptions' => $data['assumptions'] ?? null,
                'schedule' => $data['schedule'] ?? null,
                'captured_at' => Carbon::now('UTC')->toIso8601String(),
            ];

            $versionNo = (int) OperationHandoffVersion::query()->where('operation_handoff_id', $handoff->getKey())->max('version_no') + 1;

            /** @var OperationHandoffVersion $version */
            $version = parent::create([
                'operation_handoff_id' => $handoff->getKey(),
                'version_no' => $versionNo,
                'proposal_version_id' => $proposalVersion->getKey(),
                'contract_version_id' => $contractVersion?->getKey(),
                'baseline_snapshot' => $snapshot,
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: ''),
                'manifest_document_revision_id' => $data['manifest_document_revision_id'] ?? null,
                'status' => HandoffVersionStatus::Draft,
            ]);

            $order = 0;
            foreach (self::DEFAULT_ITEMS as $code => [$type, $description]) {
                HandoffItem::query()->create([
                    'handoff_version_id' => $version->getKey(),
                    'item_code' => $code,
                    'item_type' => $type,
                    'description' => $description,
                    'completion_state' => $code === 'CONTRACT' && $contractVersion !== null ? CompletionState::Complete : CompletionState::Pending,
                    'sort_order' => $order++,
                ]);
            }

            if ($handoff->status === HandoffStatus::Rejected) {
                $this->handoffs->changeStatus($handoff, HandoffStatus::Preparing);
            }

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var OperationHandoffVersion $current */
        $current = $this->show($record);

        if ($current->status !== HandoffVersionStatus::Draft) {
            throw InvalidTransitionException::make(['from' => $current->status->getLabel(), 'to' => '-']);
        }

        return parent::update($current, array_intersect_key($data, array_flip(['manifest_document_revision_id', 'decision_reason'])));
    }

    public function submit(Model|int|string $record): OperationHandoffVersion
    {
        return $this->transactions->run(function () use ($record): OperationHandoffVersion {
            /** @var OperationHandoffVersion $version */
            $version = $this->lockForUpdate($record);

            if (! $version->status->canTransitionTo(HandoffVersionStatus::Submitted)) {
                throw InvalidTransitionException::make(['from' => $version->status->getLabel(), 'to' => HandoffVersionStatus::Submitted->getLabel()]);
            }

            $pending = $version->items()
                ->whereNotIn('completion_state', [CompletionState::Complete->value, CompletionState::Waived->value, CompletionState::NotApplicable->value])
                ->count();

            if ($pending > 0) {
                throw GuardNotSatisfiedException::make(['reason' => "{$pending} devir maddesi hala bekliyor"]);
            }

            OperationHandoffVersion::query()
                ->where('operation_handoff_id', $version->operation_handoff_id)
                ->whereKeyNot($version->getKey())
                ->where('status', HandoffVersionStatus::Submitted->value)
                ->update(['status' => HandoffVersionStatus::Superseded->value]);

            $version->forceFill([
                'status' => HandoffVersionStatus::Submitted,
                'submitted_by_personnel_id' => $this->actor->personnelId(),
                'submitted_at' => Carbon::now('UTC'),
            ])->save();

            $this->recordActivity($version, 'submitted', ['surum' => $version->version_no]);

            $this->handoffs->changeStatus($version->handoff, HandoffStatus::InReview);

            return $version;
        });
    }

    private function resolveProposalVersion(int $caseId, int|string|null $requested): ProposalVersion
    {
        $query = ProposalVersion::query()->whereHas('proposal', fn ($q) => $q->where('business_case_id', $caseId));

        $version = $requested !== null && $requested !== ''
            ? $query->whereKey((int) $requested)->first()
            : $query->whereHas('proposal', fn ($q) => $q->where('is_selected', true))
                ->whereIn('status', [ProposalVersionStatus::Approved->value, ProposalVersionStatus::Submitted->value])
                ->orderByDesc('version_no')
                ->first();

        if ($version === null) {
            throw GuardNotSatisfiedException::make(['reason' => 'onayli bir teklif surumu bulunamadi']);
        }

        return $version;
    }

    private function resolveContractVersion(int $caseId, int|string|null $requested): ?ContractVersion
    {
        $query = ContractVersion::query()->whereHas('contract', fn ($q) => $q->where('business_case_id', $caseId));

        if ($requested !== null && $requested !== '') {
            return $query->whereKey((int) $requested)->first();
        }

        return $query->where('status', ContractVersionStatus::Executed->value)->orderByDesc('executed_at')->first();
    }
}
