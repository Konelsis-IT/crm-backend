<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\BoqItem;
use App\Services\AbstractService;
use App\Services\Acquisition\Concerns\GuardsVersionChildren;
use Illuminate\Database\Eloquent\Model;

/**
 * BoqItem servisi (surum cocugu, 13 SS8.2): yapi yalniz parent surum
 * taslak/incelemedeyken degisir.
 */
final class BoqItemService extends AbstractService
{
    use GuardsVersionChildren;

    protected string $model = BoqItem::class;

    protected string $orderBy = 'sort_order';

    /** @var list<string> */
    private const MUTABLE_COLUMNS = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertEstimateVersionEditable($data['estimate_version_id'] ?? null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var BoqItem $current */
        $current = $this->show($record);
        unset($data['estimate_version_id']);

        $structural = array_diff_key($data, array_flip([...self::MUTABLE_COLUMNS, 'row_version']));

        if ($structural !== []) {
            $this->assertEstimateVersionEditable($current->estimate_version_id);
        }

        return parent::update($current, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var BoqItem $current */
        $current = $this->show($record);
        $this->assertEstimateVersionEditable($current->estimate_version_id);

        return parent::delete($current);
    }
}
