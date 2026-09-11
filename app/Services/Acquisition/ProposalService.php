<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\ProposalStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif koku servisi (10 SS3.1, D-29: business case basina 1:N teklif,
 * tek secili teklif).
 *
 * create override edilmistir: proposal_no business case'in TKLF kodundan
 * uretilir (ilk teklif "TKLF-n", alternatifler "TKLF-n-B", "-C" ...); ilk
 * teklif otomatik secili olur. select() secili teklifi degistirir.
 */
final class ProposalService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['businessCase', 'owner', 'currentVersion'];

    protected string $orderBy = 'proposal_no';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var BusinessCase $case */
            $case = BusinessCase::query()->lockForUpdate()->findOrFail((int) ($data['business_case_id'] ?? 0));
            $existing = Proposal::query()->where('business_case_id', $case->getKey())->count();

            $base = 'TKLF-'.$case->sequence_no;
            $proposalNo = $existing === 0 ? $base : $base.'-'.chr(ord('A') + $existing);

            while (Proposal::query()->where('proposal_no', $proposalNo)->exists()) {
                $existing++;
                $proposalNo = $base.'-'.chr(ord('A') + $existing);
            }

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
