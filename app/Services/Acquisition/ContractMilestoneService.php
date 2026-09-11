<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\ContractMilestone;
use App\Services\AbstractService;
use App\Services\Acquisition\Concerns\GuardsVersionChildren;
use Illuminate\Database\Eloquent\Model;

/**
 * ContractMilestone servisi (surum cocugu, 13 SS8.2): yapi yalniz parent surum
 * taslak/incelemedeyken degisir.
 */
final class ContractMilestoneService extends AbstractService
{
    use GuardsVersionChildren;

    protected string $model = ContractMilestone::class;

    protected string $orderBy = 'planned_on';

    /** @var list<string> */
    private const MUTABLE_COLUMNS = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertContractVersionEditable($data['contract_version_id'] ?? null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ContractMilestone $current */
        $current = $this->show($record);
        unset($data['contract_version_id']);

        $structural = array_diff_key($data, array_flip([...self::MUTABLE_COLUMNS, 'row_version']));

        if ($structural !== []) {
            $this->assertContractVersionEditable($current->contract_version_id);
        }

        return parent::update($current, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var ContractMilestone $current */
        $current = $this->show($record);
        $this->assertContractVersionEditable($current->contract_version_id);

        return parent::delete($current);
    }
}
