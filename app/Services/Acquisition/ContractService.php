<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\ContractStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Contract;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Sozlesme koku servisi (10 SS4.1).
 *
 * create: contract_no business case sirasindan uretilir ("SZL-n" ve
 * ikinci sozlesmeden itibaren "SZL-n-2"...); musteri varsayilan olarak
 * business case'in birincil party'sidir. contracts.status surumlerden
 * turetilir (ContractVersionService).
 */
final class ContractService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['businessCase', 'customerParty', 'currentVersion'];

    protected string $orderBy = 'contract_no';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var BusinessCase $case */
            $case = BusinessCase::query()->lockForUpdate()->findOrFail((int) ($data['business_case_id'] ?? 0));
            $existing = Contract::query()->where('business_case_id', $case->getKey())->count();

            $base = 'SZL-'.$case->sequence_no;
            $contractNo = $existing === 0 ? $base : $base.'-'.($existing + 1);

            while (Contract::query()->where('contract_no', $contractNo)->exists()) {
                $existing++;
                $contractNo = $base.'-'.($existing + 1);
            }

            return parent::create([
                ...$data,
                'contract_no' => $contractNo,
                'customer_party_id' => $data['customer_party_id'] ?? $case->primary_party_id,
                'status' => ContractStatus::Draft,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['contract_no'], $data['business_case_id'], $data['current_version_id'], $data['status']);

        return parent::update($record, $data);
    }
}
