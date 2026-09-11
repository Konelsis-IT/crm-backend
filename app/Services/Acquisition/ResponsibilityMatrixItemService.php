<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\ResponsibilityMatrixItem;
use App\Services\AbstractService;
use App\Services\Acquisition\Concerns\GuardsVersionChildren;
use Illuminate\Database\Eloquent\Model;

/**
 * ResponsibilityMatrixItem servisi (surum cocugu, 13 SS8.2): yapi yalniz parent surum
 * taslak/incelemedeyken degisir; note sonradan da guncellenebilir.
 */
final class ResponsibilityMatrixItemService extends AbstractService
{
    use GuardsVersionChildren;

    protected string $model = ResponsibilityMatrixItem::class;

    protected string $orderBy = 'sort_order';

    /** @var list<string> */
    private const MUTABLE_COLUMNS = ['note'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertProposalVersionEditable($data['proposal_version_id'] ?? null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ResponsibilityMatrixItem $current */
        $current = $this->show($record);
        unset($data['proposal_version_id']);

        $structural = array_diff_key($data, array_flip([...self::MUTABLE_COLUMNS, 'row_version']));

        if ($structural !== []) {
            $this->assertProposalVersionEditable($current->proposal_version_id);
        }

        return parent::update($current, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var ResponsibilityMatrixItem $current */
        $current = $this->show($record);
        $this->assertProposalVersionEditable($current->proposal_version_id);

        return parent::delete($current);
    }
}
