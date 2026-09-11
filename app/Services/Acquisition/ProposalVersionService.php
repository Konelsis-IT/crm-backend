<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\ProposalStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\SubmissionChannel;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Teklif surumu servisi (10 SS3.2, 14 SS2.22 SM-PROP).
 *
 * create: version_no otomatik, hazirlayan aktor; kokun ilk surumu
 * current_version_id olur. update yalniz draft/review surumde calisir.
 * changeStatus: approved'da version_hash kilitlenir ve onceki
 * approved/submitted surumler superseded olur; submitted'da gonderim
 * kaniti/kanali yazilir ve business case 'submitted' asamasina tasinir.
 */
final class ProposalVersionService extends AbstractService
{
    protected string $model = ProposalVersion::class;

    /** @var list<string> */
    protected array $with = ['proposal', 'currency', 'preparer', 'approver'];

    protected string $orderBy = 'version_no';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly BusinessCaseService $businessCases,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var Proposal $proposal */
            $proposal = Proposal::query()->lockForUpdate()->findOrFail((int) ($data['proposal_id'] ?? 0));
            $versionNo = (int) ProposalVersion::query()->where('proposal_id', $proposal->getKey())->max('version_no') + 1;

            unset($data['status'], $data['version_hash'], $data['approved_by_personnel_id'], $data['approved_at'], $data['submitted_at']);

            /** @var ProposalVersion $version */
            $version = parent::create([
                ...$data,
                'proposal_id' => $proposal->getKey(),
                'version_no' => $versionNo,
                'locale' => $data['locale'] ?? 'tr',
                'currency_code' => $data['currency_code'] ?? $proposal->businessCase->currency_code,
                'status' => ProposalVersionStatus::Draft,
                'prepared_by_personnel_id' => $this->actor->personnelId() ?? $proposal->owner_employee_id,
            ]);

            if ($proposal->current_version_id === null) {
                $proposal->forceFill(['current_version_id' => $version->getKey()])->save();
            }

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ProposalVersion $current */
        $current = $this->show($record);

        if (! in_array($current->status, [ProposalVersionStatus::Draft, ProposalVersionStatus::Review], true)) {
            throw InvalidTransitionException::make(['from' => $current->status->getLabel(), 'to' => '-']);
        }

        unset($data['proposal_id'], $data['version_no'], $data['status'], $data['version_hash'], $data['approved_by_personnel_id'], $data['approved_at'], $data['submitted_at']);

        return parent::update($current, $data);
    }

    public function changeStatus(
        Model|int|string $record,
        ProposalVersionStatus $target,
        ?SubmissionChannel $channel = null,
        ?int $evidenceDocumentRevisionId = null,
    ): ProposalVersion {
        return $this->transactions->run(function () use ($record, $target, $channel, $evidenceDocumentRevisionId): ProposalVersion {
            /** @var ProposalVersion $version */
            $version = $this->lockForUpdate($record);
            $from = $version->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            /** @var Proposal $proposal */
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($version->proposal_id);
            $attributes = ['status' => $target];

            if ($target === ProposalVersionStatus::Approved) {
                $attributes['version_hash'] = $this->hashVersion($version);
                $attributes['approved_by_personnel_id'] = $this->actor->personnelId();
                $attributes['approved_at'] = Carbon::now('UTC');

                ProposalVersion::query()
                    ->where('proposal_id', $proposal->getKey())
                    ->whereKeyNot($version->getKey())
                    ->whereIn('status', [ProposalVersionStatus::Approved->value, ProposalVersionStatus::Submitted->value])
                    ->update(['status' => ProposalVersionStatus::Superseded->value]);

                $proposal->forceFill(['current_version_id' => $version->getKey(), 'status' => ProposalStatus::Approved])->save();
            }

            if ($target === ProposalVersionStatus::Submitted) {
                $attributes['submitted_at'] = Carbon::now('UTC');
                $attributes['submitted_channel'] = $channel ?? SubmissionChannel::Email;
                $attributes['submission_evidence_document_revision_id'] = $evidenceDocumentRevisionId;
                $proposal->forceFill(['status' => ProposalStatus::Submitted])->save();
            }

            if ($target === ProposalVersionStatus::Review) {
                $proposal->forceFill(['status' => ProposalStatus::InReview])->save();
            }

            if ($target === ProposalVersionStatus::Draft) {
                $proposal->forceFill(['status' => ProposalStatus::Draft])->save();
            }

            if ($target === ProposalVersionStatus::Withdrawn && $proposal->current_version_id === $version->getKey()) {
                $proposal->forceFill(['status' => ProposalStatus::Withdrawn])->save();
            }

            $version->forceFill($attributes)->save();

            $this->recordActivity($version, 'status_changed', [
                'durum' => ['onceki' => $from->value, 'yeni' => $target->value],
            ]);

            $this->syncBusinessCaseStage($proposal, $target);

            return $version;
        });
    }

    private function syncBusinessCaseStage(Proposal $proposal, ProposalVersionStatus $target): void
    {
        $case = $proposal->businessCase;

        $desired = match ($target) {
            ProposalVersionStatus::Review => AcquisitionStage::OfferReview,
            ProposalVersionStatus::Draft => AcquisitionStage::OfferPreparation,
            ProposalVersionStatus::Submitted => AcquisitionStage::Submitted,
            default => null,
        };

        if ($desired !== null && $case->acquisition_stage !== $desired && $case->acquisition_stage->canTransitionTo($desired)) {
            $this->businessCases->changeStage($case, $desired);
        }
    }

    private function hashVersion(ProposalVersion $version): string
    {
        $payload = $version->only(['proposal_id', 'version_no', 'locale', 'currency_code', 'total_price', 'margin_pct', 'validity_until', 'summary']);
        $payload['documents'] = $version->documents()->orderBy('id')->pluck('document_revision_id')->all();
        $payload['estimates'] = $version->estimateVersions()->orderBy('id')->pluck('total_price')->all();

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
