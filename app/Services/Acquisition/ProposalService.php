<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\ProposalStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Numbering\YearlyCodeAllocator;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif koku servisi (10 SS3.1, D-29: business case basina 1:N teklif,
 * tek secili teklif).
 *
 * create override edilmistir: her teklif kendi numarasini alir, TKLF-YYYY-NNNN
 * (B40, D-132; yil ve yillik sira YearlyCodeAllocator'dan). Hangi potansiyel
 * ise bagli oldugu ekranda POTIS koduyla gosterilir. B40 oncesi numara
 * potansiyel isin sirasindandir ("TKLF-n", alternatifler "-B", "-C"). Ilk
 * teklif otomatik secili olur. select() secili teklifi degistirir.
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
                'is_selected' => $existing === 0,
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

    /** Business case icin secili teklifi bu yapar (tek secili guard). */
    public function select(Model|int|string $record): Proposal
    {
        return $this->transactions->run(function () use ($record): Proposal {
            /** @var Proposal $proposal */
            $proposal = $this->lockForUpdate($record);

            Proposal::query()
                ->where('business_case_id', $proposal->business_case_id)
                ->whereKeyNot($proposal->getKey())
                ->where('is_selected', true)
                ->update(['is_selected' => false]);

            $proposal->forceFill(['is_selected' => true])->save();
            $this->recordActivity($proposal, 'selected');

            return $proposal;
        });
    }
}
