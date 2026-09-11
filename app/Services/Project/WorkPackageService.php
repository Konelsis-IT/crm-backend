<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\ProjectWorkstream;
use App\Models\Project\WbsNode;
use App\Models\Project\WorkPackage;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Is paketi servisi (11 SS1.8): project_id workstream'den turetilir, WBS
 * dugumu ayni projeden olmali, sahibi varsayilan olarak aktordur.
 */
final class WorkPackageService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = WorkPackage::class;

    /** @var list<string> */
    protected array $with = ['workstream.group', 'wbsNode', 'owner'];

    protected string $orderBy = 'package_code';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $projectId = (int) ProjectWorkstream::query()->whereKey((int) ($data['project_workstream_id'] ?? 0))->value('project_id');
        $this->assertSameProject($projectId, WbsNode::class, $data['wbs_node_id'] ?? null);

        return parent::create([
            ...$data,
            'project_id' => $projectId,
            'owner_personnel_id' => $data['owner_personnel_id'] ?? $this->actor->personnelId(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var WorkPackage $current */
        $current = $this->show($record);
        unset($data['project_id'], $data['project_workstream_id']);

        if (array_key_exists('wbs_node_id', $data)) {
            $this->assertSameProject((int) $current->project_id, WbsNode::class, $data['wbs_node_id']);
        }

        return parent::update($current, $data);
    }
}
