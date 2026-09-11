<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\Project\SameProjectRequiredException;
use App\Models\Project\StageDependency;
use App\Models\Project\StageNode;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Project\Concerns\DetectsDependencyCycles;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Gate bagimliligi servisi (11 SS2.4): iki gate ayni sablon surumunden
 * olmali, dongu olmamali, surum taslak olmali.
 */
final class StageDependencyService extends AbstractService
{
    use DetectsDependencyCycles;

    protected string $model = StageDependency::class;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly StageTemplateVersionService $versions,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->validateEdge((int) ($data['predecessor_node_id'] ?? 0), (int) ($data['successor_node_id'] ?? 0), null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var StageDependency $current */
        $current = $this->show($record);
        $this->validateEdge(
            (int) ($data['predecessor_node_id'] ?? $current->predecessor_node_id),
            (int) ($data['successor_node_id'] ?? $current->successor_node_id),
            (int) $current->getKey(),
        );

        return parent::update($current, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var StageDependency $current */
        $current = $this->show($record);
        $this->versions->assertDraft($current->predecessor->templateVersion);

        return parent::delete($current);
    }

    private function validateEdge(int $predecessor, int $successor, ?int $exceptId): void
    {
        $versions = StageNode::query()->whereKey([$predecessor, $successor])->pluck('stage_template_version_id', 'id');

        if ($versions->count() !== 2 || $versions->unique()->count() !== 1) {
            throw SameProjectRequiredException::make();
        }

        $this->versions->assertDraft(StageNode::query()->findOrFail($predecessor)->templateVersion);

        $edges = [];
        StageDependency::query()
            ->whereIn('predecessor_node_id', StageNode::query()->where('stage_template_version_id', $versions->first())->select('id'))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->get(['predecessor_node_id', 'successor_node_id'])
            ->each(function (StageDependency $dependency) use (&$edges): void {
                $edges[(int) $dependency->predecessor_node_id][] = (int) $dependency->successor_node_id;
            });

        $this->assertNoCycle($edges, $predecessor, $successor);
    }
}
