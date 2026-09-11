<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\ProjectRisk;
use App\Models\Project\ProjectWorkstream;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Project\Concerns\NumbersProjectRecords;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Proje riski servisi (11 SS3.14): RSK-001 numarasi; skor DB'de
 * (probability * impact) uretilir.
 */
final class ProjectRiskService extends AbstractService
{
    use ChecksProjectScope, NumbersProjectRecords;

    protected string $model = ProjectRisk::class;

    /** @var list<string> */
    protected array $with = ['workstream.group', 'owner'];

    protected string $orderBy = 'score';

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
        $this->assertSameProject($projectId, ProjectWorkstream::class, $data['workstream_id'] ?? null);

        return parent::create([
            ...$data,
            'risk_no' => $this->nextProjectNumber(ProjectRisk::class, $projectId, 'risk_no', 'RSK'),
            'owner_personnel_id' => $data['owner_personnel_id'] ?? $this->actor->personnelId(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ProjectRisk $current */
        $current = $this->show($record);
        unset($data['project_id'], $data['risk_no'], $data['score']);

        if (array_key_exists('workstream_id', $data)) {
            $this->assertSameProject((int) $current->project_id, ProjectWorkstream::class, $data['workstream_id']);
        }

        return parent::update($current, $data);
    }
}
