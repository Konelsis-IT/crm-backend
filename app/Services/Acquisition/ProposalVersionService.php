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
use App\Services\Platform\SchemaReadiness;
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
 * D-195 (B55): her surumun kendi teklif durumu (offer_status) vardir; ilk surum
 * teklifin durumunu alir, yeni surum Verilecek baslar, onceki surum kendi
 * durumunu korur. Teklifteki kopyayi ProposalService yazar.
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
            $offer = $data['offer_status'] ?? null;

            unset($data['status'], $data['version_hash'], $data['approved_by_personnel_id'], $data['approved_at'], $data['submitted_at'], $data['offer_status']);

            /** @var ProposalVersion $version */
            $version = parent::create([
                ...$data,
                'proposal_id' => $proposal->getKey(),
                'version_no' => $versionNo,
                'locale' => $data['locale'] ?? 'tr',
                'currency_code' => $data['currency_code'] ?? $proposal->businessCase->currency_code,
                'status' => ProposalVersionStatus::Draft,
                'prepared_by_personnel_id' => $this->actor->personnelId() ?? $proposal->owner_employee_id,
                ...(SchemaReadiness::hasBatch('B55') ? ['offer_status' => $this->initialOfferStatus($proposal, $offer)] : []),
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

        // D-195: surumun teklif durumu yalniz setOfferStatus ile (teklifteki kopyayla birlikte) degisir.
        unset($data['proposal_id'], $data['version_no'], $data['status'], $data['version_hash'], $data['approved_by_personnel_id'], $data['approved_at'], $data['submitted_at'], $data['offer_status']);

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
     * D-195 (B55, 12 Ekim 2026 kullanici karari: "Her revizyonda yeni surum
     * gelsin, oncekinin durumu da kaybedilmesin. Yani her revizyon'un durumu
     * kendine ait olsun"): yeni surum "Verilecek teklif" baslar ve teklifin
     * teklif durumu (proposals.offer_status kopyasi) da Verilecek olur; onceki
     * surum kendi teklif durumunu (or. Verilen) korur, is akisi durumu "Yerini
     * aldi" olur. Potansiyel isin asamasi geri alinmaz. B55 oncesi teklif durumu
     * degismez.
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
                'offer_status' => OfferStatus::ToBeSubmitted,
            ]);

            if ($previous !== null && ! in_array($previous->status, [ProposalVersionStatus::Superseded, ProposalVersionStatus::Withdrawn], true)) {
                $from = $previous->status;
                $previous->forceFill(['status' => ProposalVersionStatus::Superseded])->save();
                $this->recordActivity($previous, 'superseded', [
                    'durum' => ['onceki' => $from->value, 'yeni' => ProposalVersionStatus::Superseded->value],
                    'yeni_surum' => $version->version_no,
                    // D-195: onceki surumun kendi teklif durumu korunur (kayitta gorunsun).
                    ...(SchemaReadiness::hasBatch('B55') ? ['teklif_durumu' => $previous->offer_status?->value] : []),
                ]);
            }

            $attributes = ['current_version_id' => $version->getKey()];

            if (in_array($locked->status, [ProposalStatus::InReview, ProposalStatus::Approved, ProposalStatus::Submitted], true)) {
                $attributes['status'] = ProposalStatus::Draft;
            }

            $locked->forceFill($attributes)->save();

            // D-195: teklifteki kopya yeni guncel surumun durumunu (Verilecek) alir.
            app(ProposalService::class)->followCurrentVersion($locked, $version, 'offer_status_new_version');

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
            $wasCurrent = (int) $proposal->current_version_id === (int) $version->getKey();
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

            $offers = app(ProposalService::class);
            $b55 = SchemaReadiness::hasBatch('B55');

            // D-195: onaylanan eski surum guncel surum olduysa teklifteki kopya onun
            // kendi teklif durumunu alir.
            if ($b55 && ! $wasCurrent && (int) $proposal->current_version_id === (int) $version->getKey()) {
                $offers->followCurrentVersion($proposal, $version, 'offer_status_synced');
            }

            // D-161: musteriye gonderilen teklifin "Teklif durumu" Verilen olur
            // (yalniz Verilecek / bos ise; Onaylandi ve Kacan firsat korunur).
            // D-195: guncel olmayan surum gonderildiyse yalniz o surumun kendi
            // durumu degisir; teklifin durumu guncel surumundur.
            if ($target === ProposalVersionStatus::Submitted) {
                if ($b55 && (int) $proposal->current_version_id !== (int) $version->getKey()) {
                    if (in_array($version->offer_status, [null, OfferStatus::ToBeSubmitted], true)) {
                        $this->setOfferStatus($version, OfferStatus::Submitted, 'offer_status_synced');
                    }
                } else {
                    $offers->syncOfferStatus([$proposal], OfferStatus::Submitted, [OfferStatus::ToBeSubmitted]);
                }
            }

            return $version;
        });
    }

    /**
     * Surumun kendi teklif durumu (D-195, B55). Teklifteki kopyayi
     * ProposalService yazar (changeOfferStatus / syncOfferStatus /
     * followCurrentVersion); bu yontem yalniz surumu yazar ve Personel
     * Hareketleri'ne "proposal_version.{islem}" kaydeder. B55 uygulanmadiysa
     * hicbir sey yapilmaz.
     *
     * @param  array<string, mixed>  $context  kayda eklenecek bilgiler (gerekce ...)
     */
    public function setOfferStatus(Model|int|string $record, ?OfferStatus $target, string $operation = 'offer_status_changed', array $context = []): ?ProposalVersion
    {
        if (! SchemaReadiness::hasBatch('B55')) {
            return null;
        }

        return $this->transactions->run(function () use ($record, $target, $operation, $context): ProposalVersion {
            /** @var ProposalVersion $version */
            $version = $this->lockForUpdate($record);
            $from = $version->offer_status;

            if ($from === $target) {
                return $version;
            }

            $version->forceFill(['offer_status' => $target]);
            $this->saveWithoutVersion($version);
            $this->recordActivity($version, $operation, [
                'teklif_durumu' => ['onceki' => $from?->value, 'yeni' => $target?->value],
                'surum' => $version->version_no,
                ...array_filter($context, static fn (mixed $value): bool => $value !== null),
            ]);

            return $version;
        });
    }

    /**
     * Yeni surumun ilk teklif durumu (D-195, B55): verilen deger; yoksa teklifin
     * ilk surumu teklifin bugunku durumunu alir (yeni teklif, liste aktarimi),
     * sonraki surumler "Verilecek teklif" baslar.
     */
    private function initialOfferStatus(Proposal $proposal, mixed $value): ?OfferStatus
    {
        if ($value instanceof OfferStatus) {
            return $value;
        }

        if (is_string($value) && OfferStatus::tryFrom($value) !== null) {
            return OfferStatus::from($value);
        }

        return $proposal->current_version_id === null ? $proposal->offer_status : OfferStatus::ToBeSubmitted;
    }

    /**
     * Olusturma kaydinda surumun teklif durumu da gorunur (D-195, B55).
     *
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $summary = parent::createdChanges($record);
        $offer = SchemaReadiness::hasBatch('B55') ? $record->getAttribute('offer_status') : null;

        if ($offer instanceof OfferStatus) {
            $summary['teklif_durumu'] = $offer->value;
        }

        return $summary;
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
