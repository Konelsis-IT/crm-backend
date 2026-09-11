<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\DependencyStatus;
use App\Exceptions\Project\SameProjectRequiredException;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\WorkstreamDependency;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\DetectsDependencyCycles;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Workstream bagimliligi servisi (11 SS1.6): ayni proje kontrolu, dongu
 * kontrolu, muafiyette muaf tutan kisi.
 */
final class WorkstreamDependencyService extends AbstractService
{
    use DetectsDependencyCycles;

    protected string $model = WorkstreamDependency::class;

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
        $this->validateEdge((int) ($data['predecessor_workstream_id'] ?? 0), (int) ($data['successor_workstream_id'] ?? 0), null);

        return parent::create($this->stampWaiver($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var WorkstreamDependency $current */
        $current = $this->show($record);
        $predecessor = (int) ($data['predecessor_workstream_id'] ?? $current->predecessor_workstream_id);
        $successor = (int) ($data['successor_workstream_id'] ?? $current->successor_workstream_id);
        $this->validateEdge($predecessor, $successor, (int) $current->getKey());

        return parent::update($current, $this->stampWaiver($data));
    }

    private function validateEdge(int $predecessor, int $successor, ?int $exceptId): void
    {
        $projects = ProjectWorkstream::query()->whereKey([$predecessor, $successor])->pluck('project_id', 'id');

        if ($projects->count() !== 2 || $projects->unique()->count() !== 1) {
            throw SameProjectRequiredException::make();
        }

        $edges = [];
        WorkstreamDependency::query()
            ->whereIn('predecessor_workstream_id', ProjectWorkstream::query()->where('project_id', $projects->first())->select('id'))
            ->where('status', DependencyStatus::Active->value)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->get(['predecessor_workstream_id', 'successor_workstream_id'])
            ->each(function (WorkstreamDependency $dependency) use (&$edges): void {
                $edges[(int) $dependency->predecessor_workstream_id][] = (int) $dependency->successor_workstream_id;
            });

        $this->assertNoCycle($edges, $predecessor, $successor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampWaiver(array $data): array
    {
        $status = $data['status'] ?? null;
        $status = $status instanceof DependencyStatus ? $status->value : $status;

        if ($status === DependencyStatus::Waived->value) {
            $data['waived_by_personnel_id'] = $this->actor->personnelId();
        }

        return $data;
    }
}
