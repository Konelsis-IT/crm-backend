<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\Milestone;
use App\Models\Project\WbsNode;
use App\Services\AbstractService;
use App\Services\Project\Concerns\ChecksProjectScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Kilometre tasi servisi (11 SS3.10): WBS dugumu ayni projeden olmali.
 */
final class MilestoneService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = Milestone::class;

    /** @var list<string> */
    protected array $with = ['wbsNode', 'contractMilestone'];

    protected string $orderBy = 'planned_at';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertSameProject((int) ($data['project_id'] ?? 0), WbsNode::class, $data['wbs_node_id'] ?? null);
        $data['milestone_code'] = strtoupper(trim((string) ($data['milestone_code'] ?? '')));

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var Milestone $current */
        $current = $this->show($record);
        unset($data['project_id']);

        if (array_key_exists('wbs_node_id', $data)) {
            $this->assertSameProject((int) $current->project_id, WbsNode::class, $data['wbs_node_id']);
        }

        return parent::update($current, $data);
    }
}
