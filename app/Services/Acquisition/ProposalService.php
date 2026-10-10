<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Numbering\YearlyCodeAllocator;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif koku servisi (10 SS3.1, D-29: business case basina 1:N teklif).
 *
 * create override edilmistir: her teklif kendi numarasini alir, TKLF-YYYY-NNNN
 * (B40, D-132; yil ve yillik sira YearlyCodeAllocator'dan). Hangi potansiyel
 * ise bagli oldugu ekranda POTIS koduyla gosterilir. B40 oncesi numara
 * potansiyel isin sirasindandir ("TKLF-n", alternatifler "-B", "-C").
 *
 * D-181 (9 Ekim 2026): "secili teklif" kavrami kaldirildi. select() ve ilk
 * teklifi otomatik secili yapma kalkti; is her zaman en son tekliften ve onun
 * guncel surumunden devam eder. proposals.is_selected kolonu (ve tekil
 * selected_guard) veritabaninda kalir, uygulama artik yazmaz ve okumaz.
 */
final class ProposalService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['businessCase', 'owner', 'currentVersion'];

    protected string $orderBy = 'proposal_no';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly YearlyCodeAllocator $codes,
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
            $existing = Proposal::query()->where('business_case_id', $case->getKey())->count();
            $proposalNo = $this->codes->enabled()
                ? $this->codes->next(BusinessCodeKind::Offer->prefix())['formatted_code']
                : $this->legacyNumber($case, $existing);

            return parent::create([
                ...$data,
                'proposal_no' => $proposalNo,
                'title' => $data['title'] ?? $case->title,
                'owner_employee_id' => $data['owner_employee_id'] ?? $case->proposal_owner_employee_id ?? $case->owner_employee_id,
                'status' => ProposalStatus::Draft,
            ]);
        });
    }

    /** B40 oncesi numara: potansiyel isin sirasindan "TKLF-n", alternatifler "-B", "-C"... */
    private function legacyNumber(BusinessCase $case, int $existing): string
    {
        $base = 'TKLF-'.$case->sequence_no;
        $proposalNo = $existing === 0 ? $base : $base.'-'.chr(ord('A') + $existing);

        while (Proposal::query()->where('proposal_no', $proposalNo)->exists()) {
            $existing++;
            $proposalNo = $base.'-'.chr(ord('A') + $existing);
        }

        return $proposalNo;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['proposal_no'], $data['business_case_id'], $data['current_version_id'], $data['status'], $data['is_selected']);

        return parent::update($record, $data);
    }

    /**
     * Teklif durumunun elle gecilebilecegi yeni degerler (D-182, 9 Ekim 2026
     * kullanici talimati: "Bu teklifin durumu Gonderildi degil, Verilen teklif.
     * Ve action'a koyacaksin, dropdown ile secilecek").
     *
     * Yalniz ileri: Verilecek (ya da bos) -> Verilen / Onaylandi / Kacan firsat;
     * Verilen -> Onaylandi / Kacan firsat. Onaylandi ve Kacan firsat kapanistir.
     * Geri donus (Verilen -> Verilecek) yok: gonderilen surum geri alinamaz
     * (ProposalVersionStatus: Gonderildi'den yalniz "Yerini aldi"). Potansiyel is
     * kaybedildi / iptal edildiyse Onaylandi sunulmaz (is Kazanildi olamaz).
     *
     * @return list<OfferStatus>
     */
    public function offerTargets(Proposal $proposal): array
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            return [];
        }

        $targets = match ($proposal->offer_status) {
            null, OfferStatus::ToBeSubmitted => [OfferStatus::Submitted, OfferStatus::Approved, OfferStatus::Lost],
            OfferStatus::Submitted => [OfferStatus::Approved, OfferStatus::Lost],
            OfferStatus::Approved, OfferStatus::Lost => [],
        };

        $stage = $proposal->businessCase?->acquisition_stage;

        if (in_array($stage, [AcquisitionStage::Lost, AcquisitionStage::Cancelled], true)) {
            $targets = array_values(array_filter($targets, static fn (OfferStatus $target): bool => $target !== OfferStatus::Approved));
        }

        return $targets;
    }

    /**
     * Teklif durumunu degistirir (D-182); yeni teklif surumu acilmaz (D-161).
     *
     * - Verilen: guncel surum Inceleme / Onaylandi uzerinden Gonderildi'ye
     *   yurur (gonderim tarihi simdi; KksListUpdate20261007Seeder::submitVersion
     *   ile ayni yol), potansiyel is en az "Teklif verildi" olur.
     * - Onaylandi: ayni gonderim yolu, ardindan potansiyel is Kazanildi
     *   (zaten kazanildi ya da devirdeyse dokunulmaz).
     * - Kacan firsat: yalniz teklif durumu; isin butun teklifleri kacan firsat
     *   olduysa potansiyel is Kaybedildi olur (KksProposalSeeder::closeIfAllLost
     *   kurali). $reason etkinlik gecmisine yazilir.
     */
    public function changeOfferStatus(Proposal $proposal, OfferStatus $target, ?string $reason = null): Proposal
    {
        $reason = filled($reason) ? trim((string) $reason) : null;

        return $this->transactions->run(function () use ($proposal, $target, $reason): Proposal {
            /** @var Proposal $locked */
            $locked = $this->lockForUpdate($proposal);
            $from = $locked->offer_status;

            if (! in_array($target, $this->offerTargets($locked), true)) {
                throw InvalidTransitionException::make(['from' => $from?->getLabel() ?? '-', 'to' => $target->getLabel()]);
            }

            // Once teklif durumu: surumun Gonderildi'ye yurumesi Onaylandi'yi
            // "Verilen"e cevirmesin (syncOfferStatus yalniz Verilecek'i degistirir).
            $locked->forceFill(['offer_status' => $target]);
            $this->saveWithoutVersion($locked);
            $this->recordActivity($locked, 'offer_status_changed', [
                'teklif_durumu' => ['onceki' => $from?->value, 'yeni' => $target->value],
                'gerekce' => $reason,
            ]);

            if (in_array($target, [OfferStatus::Submitted, OfferStatus::Approved], true)) {
                $this->submitCurrentVersion($locked);
                $this->advanceCase($locked, $target === OfferStatus::Approved ? AcquisitionStage::Won : AcquisitionStage::Submitted);
            }

            if ($target === OfferStatus::Lost) {
                $this->closeCaseIfAllLost($locked, $reason);
            }

            $proposal->refresh();
            $proposal->unsetRelation('currentVersion');
            $proposal->unsetRelation('businessCase');

            return $proposal;
        });
    }

    /** Guncel surum izinli gecislerle Gonderildi'ye yurur (D-182). */
    private function submitCurrentVersion(Proposal $proposal): void
    {
        $versions = app(ProposalVersionService::class);

        foreach ([ProposalVersionStatus::Review, ProposalVersionStatus::Approved, ProposalVersionStatus::Submitted] as $step) {
            /** @var ProposalVersion|null $version */
            $version = $proposal->current_version_id === null ? null : ProposalVersion::query()->find($proposal->current_version_id);

            if ($version === null || ! $version->status->canTransitionTo($step)) {
                continue;
            }

            $versions->changeStatus((int) $version->getKey(), $step);
        }
    }

    /**
     * Potansiyel isi yalniz ileri dogru hedef asamaya yurutur (D-182):
     * dogrudan gecis varsa o, yoksa Is gelistirme -> Teklif hazirlaniyor ->
     * Teklif incelemede -> Teklif verildi sirasi. Muzakere, kazanilmis, devir ya
     * da kapanmis isler geri cekilmez.
     */
    private function advanceCase(Proposal $proposal, AcquisitionStage $target): void
    {
        /** @var BusinessCase|null $case */
        $case = BusinessCase::query()->find($proposal->business_case_id);

        if ($case === null) {
            return;
        }

        $path = [AcquisitionStage::BusinessDevelopment, AcquisitionStage::OfferPreparation, AcquisitionStage::OfferReview, AcquisitionStage::Submitted];
        $cases = app(BusinessCaseService::class);

        while ($case->acquisition_stage !== $target) {
            $stage = $case->acquisition_stage;

            if ($stage->canTransitionTo($target)) {
                $cases->changeStage($case, $target);
                break;
            }

            $index = array_search($stage, $path, true);
            $next = $index === false ? null : ($path[$index + 1] ?? null);

            if ($next === null || ! $stage->canTransitionTo($next)) {
                break;
            }

            $cases->changeStage($case, $next);
            $case->refresh();
        }
    }

    /** Isin butun teklifleri kacan firsatsa potansiyel is Kaybedildi (D-182). */
    private function closeCaseIfAllLost(Proposal $proposal, ?string $reason): void
    {
        $statuses = Proposal::query()->where('business_case_id', $proposal->business_case_id)->pluck('offer_status');

        if ($statuses->isEmpty() || $statuses->contains(static fn (?OfferStatus $status): bool => $status !== OfferStatus::Lost)) {
            return;
        }

        /** @var BusinessCase|null $case */
        $case = BusinessCase::query()->find($proposal->business_case_id);

        if ($case === null || ! $case->acquisition_stage->canTransitionTo(AcquisitionStage::Lost)) {
            return;
        }

        app(BusinessCaseService::class)->changeStage($case, AcquisitionStage::Lost, $reason);
    }

    /**
     * Teklifin ticari durumunu (Teklif durumu: Verilecek / Verilen / Kacan
     * firsat) is akisiyla esler (D-161, 6 Ekim 2026: iki durum alani birbirine
     * baglandi). $onlyFrom verilirse yalniz o durumlardaki teklifler degisir.
     * B29 uygulanmadiysa hicbir sey yapilmaz.
     *
     * @param  iterable<Proposal>  $proposals
     * @param  list<OfferStatus>|null  $onlyFrom  null: hedeften farkli her durum
     */
    public function syncOfferStatus(iterable $proposals, OfferStatus $target, ?array $onlyFrom = null): void
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            return;
        }

        $this->transactions->run(function () use ($proposals, $target, $onlyFrom): void {
            foreach ($proposals as $proposal) {
                $current = $proposal->offer_status;

                if ($current === $target || ($onlyFrom !== null && $current !== null && ! in_array($current, $onlyFrom, true))) {
                    continue;
                }

                $proposal->forceFill(['offer_status' => $target]);
                $this->saveWithoutVersion($proposal);
                $this->recordActivity($proposal, 'offer_status_synced', [
                    'teklif_durumu' => ['onceki' => $current?->value, 'yeni' => $target->value],
                ]);
            }
        });
    }
}
