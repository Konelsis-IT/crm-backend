<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\SubmissionChannel;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Services\AbstractService;
use App\Services\Acquisition\Concerns\ProposalAmendment;
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
 * revise (B43, D-155): yeni guncel surum acar. D-186: yalniz personelin
 * "Yeni teklif surumu" dugmesiyle; "Duzenle" guncel surumu yerinde degistirir
 * (update + ProposalAmendment, refreshHash).
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

        // D-186: teklif "Duzenle" guncel surumu durumundan bagimsiz yerinde duzeltir
        // (ProposalAmendment yalniz o surum ve o cagri icin izin verir).
        if (! ProposalAmendment::allows($current->getKey()) && ! in_array($current->status, [ProposalVersionStatus::Draft, ProposalVersionStatus::Review], true)) {
            throw InvalidTransitionException::make(['from' => $current->status->getLabel(), 'to' => '-']);
        }

        unset($data['proposal_id'], $data['version_no'], $data['status'], $data['version_hash'], $data['approved_by_personnel_id'], $data['approved_at'], $data['submitted_at']);

        return parent::update($current, $data);
    }

    /**
     * Teklifin yeni surumu (B43, D-155; D-186 ile yalniz "Yeni teklif surumu"
     * dugmesinden: "Komple teklifte yeni surum secenegi olacak ... bu hamle teklif
     * surumunu 2 yapacaktir"). Yeni surum taslak acilir ve teklifin
     * guncel surumu olur; onceki guncel surum "superseded" olur — taslak ya da
     * incelemedeki surum icin de (normal gecis tablosunun disinda, yalniz bu
     * yolda). Teklif kokunun surume bagli durumu (inceleme / onay / gonderim)
     * taslaga doner; muzakere ve sonuc durumlari korunur.
     *
     * @param  array<string, mixed>  $data  surum alanlari
     */
    public function revise(Proposal $proposal, array $data): ProposalVersion
    {
        return $this->transactions->run(function () use ($proposal, $data): ProposalVersion {
            /** @var Proposal $locked */
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->getKey());
            /** @var ProposalVersion|null $previous */
            $previous = $locked->current_version_id === null ? null : ProposalVersion::query()->lockForUpdate()->find($locked->current_version_id);

            /** @var ProposalVersion $version */
            $version = $this->create([
                ...$data,
                'proposal_id' => $locked->getKey(),
                'locale' => $data['locale'] ?? $previous?->locale,
                'currency_code' => $data['currency_code'] ?? $previous?->currency_code,
            ]);

            if ($previous !== null && ! in_array($previous->status, [ProposalVersionStatus::Superseded, ProposalVersionStatus::Withdrawn], true)) {
                $from = $previous->status;
                $previous->forceFill(['status' => ProposalVersionStatus::Superseded])->save();
                $this->recordActivity($previous, 'superseded', [
                    'durum' => ['onceki' => $from->value, 'yeni' => ProposalVersionStatus::Superseded->value],
                    'yeni_surum' => $version->version_no,
                ]);
            }

            $attributes = ['current_version_id' => $version->getKey()];

            if (in_array($locked->status, [ProposalStatus::InReview, ProposalStatus::Approved, ProposalStatus::Submitted], true)) {
                $attributes['status'] = ProposalStatus::Draft;
            }

            $locked->forceFill($attributes)->save();

            return $version;
        });
    }

    /**
     * $submittedAt: gonderim tarihi bilinen eski teklifler icin (liste
     * aktarimi); verilmezse simdiki an yazilir.
     */
    public function changeStatus(
        Model|int|string $record,
        ProposalVersionStatus $target,
        ?SubmissionChannel $channel = null,
        ?int $evidenceDocumentRevisionId = null,
        ?Carbon $submittedAt = null,
    ): ProposalVersion {
        return $this->transactions->run(function () use ($record, $target, $channel, $evidenceDocumentRevisionId, $submittedAt): ProposalVersion {
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
                $attributes['submitted_at'] = $submittedAt?->copy()->utc() ?? Carbon::now('UTC');
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

            // D-161: musteriye gonderilen teklifin "Teklif durumu" Verilen olur
            // (yalniz Verilecek / bos ise; Onaylandi ve Kacan firsat korunur).
            if ($target === ProposalVersionStatus::Submitted) {
                app(ProposalService::class)->syncOfferStatus([$proposal], OfferStatus::Submitted, [OfferStatus::ToBeSubmitted]);
            }

            return $version;
        });
    }

    /**
     * Liste aktarimiyla yazilmis ozetin duzeltilmesi (D-137: gorusme notlari
     * ozetten cikip gorusme notu kaydina tasindi). Surum durumundan bagimsiz
     * calisir; onayli surumun ozeti hash'e girdiginden hash yeniden hesaplanir.
     * Eski ozet etkinlik gecmisinde kalir.
     */
    public function correctImportedSummary(Model|int|string $record, string $summary): ProposalVersion
    {
        return $this->transactions->run(function () use ($record, $summary): ProposalVersion {
            /** @var ProposalVersion $version */
            $version = $this->lockForUpdate($record);
            $previous = (string) $version->summary;

            if ($previous === $summary) {
                return $version;
            }

            $version->forceFill(['summary' => $summary]);

            if ($version->version_hash !== null) {
                $version->forceFill(['version_hash' => $this->hashVersion($version)]);
            }

            $version->save();

            $this->recordActivity($version, 'summary_corrected', ['onceki_ozet' => $previous]);

            return $version;
        });
    }

    /**
     * D-186: yerinde duzeltilen onayli / gonderilmis surumun ozeti (hash) yeni
     * icerikle yeniden hesaplanir; hash'i olmayan surume dokunulmaz.
     */
    public function refreshHash(ProposalVersion $version): void
    {
        $version->refresh();

        if ($version->version_hash === null) {
            return;
        }

        $hash = $this->hashVersion($version);

        if ($hash !== $version->version_hash) {
            $version->forceFill(['version_hash' => $hash])->save();
        }
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
