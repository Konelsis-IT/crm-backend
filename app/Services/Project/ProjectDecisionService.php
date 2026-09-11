<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\ProjectDecision;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\NumbersProjectRecords;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje karar kaydi servisi (11 SS3.20): append-only; DEC-001 numarasi,
 * karar veren/tarih varsayilani.
 */
final class ProjectDecisionService extends AbstractService
{
    use NumbersProjectRecords;

    protected string $model = ProjectDecision::class;

    /** @var list<string> */
    protected array $with = ['decider', 'documentRevision'];

    protected string $orderBy = 'decided_at';

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

        return parent::create([
            ...$data,
            'decision_no' => $this->nextProjectNumber(ProjectDecision::class, $projectId, 'decision_no', 'DEC'),
            'personnel_id' => $data['personnel_id'] ?? $this->actor->personnelId(),
            'decided_at' => $data['decided_at'] ?? Carbon::now('UTC'),
        ]);
    }
}
