<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\DelayEvent;
use App\Models\Project\ProjectWorkstream;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gecikme olayi servisi (11 SS3.15): workstream ayni projeden, bildiren ve
 * tespit tarihi varsayilani.
 */
final class DelayEventService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = DelayEvent::class;

    /** @var list<string> */
    protected array $with = ['workstream.group', 'reporter', 'evidenceRevision'];

    protected string $orderBy = 'detected_at';

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
            'reported_by_personnel_id' => $data['reported_by_personnel_id'] ?? $this->actor->personnelId(),
            'detected_at' => $data['detected_at'] ?? Carbon::now('UTC'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var DelayEvent $current */
        $current = $this->show($record);
        unset($data['project_id']);

        if (array_key_exists('workstream_id', $data)) {
            $this->assertSameProject((int) $current->project_id, ProjectWorkstream::class, $data['workstream_id']);
        }

        return parent::update($current, $data);
    }
}
