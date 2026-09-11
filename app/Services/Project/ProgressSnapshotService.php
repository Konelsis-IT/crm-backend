<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\ProgressSource;
use App\Models\Project\ProgressSnapshot;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ilerleme fotografi servisi (11 SS3.11): append-only; bildiren, an ve
 * kaynak varsayilani.
 */
final class ProgressSnapshotService extends AbstractService
{
    protected string $model = ProgressSnapshot::class;

    /** @var list<string> */
    protected array $with = ['reporter'];

    protected string $orderBy = 'snapshot_at';

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
        return parent::create([
            ...$data,
            'snapshot_at' => $data['snapshot_at'] ?? Carbon::now('UTC'),
            'source' => $data['source'] ?? ProgressSource::Manual,
            'reported_by_personnel_id' => $data['reported_by_personnel_id'] ?? $this->actor->personnelId(),
        ]);
    }
}
