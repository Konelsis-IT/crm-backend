<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\Project\AllocationExceededException;
use App\Exceptions\Project\SameProjectRequiredException;
use App\Models\Project\CbsNode;
use App\Models\Project\WbsCbsMapping;
use App\Models\Project\WbsNode;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * WBS-CBS eslemesi servisi (11 SS3.7): ayni WBS dugumu icin dagilim toplami
 * 100'u asamaz; iki dugum ayni projeden olmali.
 */
final class WbsCbsMappingService extends AbstractService
{
    protected string $model = WbsCbsMapping::class;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->validate((int) ($data['wbs_node_id'] ?? 0), (int) ($data['cbs_node_id'] ?? 0), (float) ($data['allocation_pct'] ?? 0), null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var WbsCbsMapping $current */
        $current = $this->show($record);
        $this->validate(
            (int) ($data['wbs_node_id'] ?? $current->wbs_node_id),
            (int) ($data['cbs_node_id'] ?? $current->cbs_node_id),
            (float) ($data['allocation_pct'] ?? $current->allocation_pct),
            (int) $current->getKey(),
        );

        return parent::update($current, $data);
    }

    private function validate(int $wbsNodeId, int $cbsNodeId, float $allocation, ?int $exceptId): void
    {
        $wbsProject = WbsNode::query()->whereKey($wbsNodeId)->value('project_id');
        $cbsProject = CbsNode::query()->whereKey($cbsNodeId)->value('project_id');

        if ($wbsProject === null || $cbsProject === null || (int) $wbsProject !== (int) $cbsProject) {
            throw SameProjectRequiredException::make();
        }

        $existing = (float) WbsCbsMapping::query()
            ->where('wbs_node_id', $wbsNodeId)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('allocation_pct');

        $total = $existing + $allocation;

        if ($total > 100.0001) {
            throw AllocationExceededException::make(['total' => number_format($total, 2, '.', '')]);
        }
    }
}
