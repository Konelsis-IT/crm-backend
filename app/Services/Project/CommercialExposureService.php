<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\CbsNode;
use App\Models\Project\CommercialExposure;
use App\Models\Project\Project;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Project\Concerns\NumbersProjectRecords;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Ticari maruziyet servisi (11 SS3.19): EXP-001 numarasi, CBS dugumu ayni
 * projeden, para birimi varsayilan olarak projenin.
 */
final class CommercialExposureService extends AbstractService
{
    use ChecksProjectScope, NumbersProjectRecords;

    protected string $model = CommercialExposure::class;

    /** @var list<string> */
    protected array $with = ['cbsNode', 'currency', 'owner'];

    protected string $orderBy = 'exposure_amount';

    protected string $orderDirection = 'desc';

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
        $projectId = (int) ($data['project_id'] ?? 0);
        $this->assertSameProject($projectId, CbsNode::class, $data['cbs_node_id'] ?? null);

        return parent::create([
            ...$data,
            'exposure_no' => $this->nextProjectNumber(CommercialExposure::class, $projectId, 'exposure_no', 'EXP'),
            'owner_personnel_id' => $data['owner_personnel_id'] ?? $this->actor->personnelId(),
            'currency_code' => $data['currency_code'] ?? Project::query()->whereKey($projectId)->value('currency_code'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var CommercialExposure $current */
        $current = $this->show($record);
        unset($data['project_id'], $data['exposure_no']);

        if (array_key_exists('cbs_node_id', $data)) {
            $this->assertSameProject((int) $current->project_id, CbsNode::class, $data['cbs_node_id']);
        }

        return parent::update($current, $data);
    }
}
