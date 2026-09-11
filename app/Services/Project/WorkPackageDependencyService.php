<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\Project\SameProjectRequiredException;
use App\Models\Project\WorkPackage;
use App\Models\Project\WorkPackageDependency;
use App\Services\AbstractService;
use App\Services\Project\Concerns\DetectsDependencyCycles;
use Illuminate\Database\Eloquent\Model;

/**
 * Is paketi bagimliligi servisi (11 SS1.9): ayni proje ve dongu kontrolu.
 */
final class WorkPackageDependencyService extends AbstractService
{
    use DetectsDependencyCycles;

    protected string $model = WorkPackageDependency::class;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->validateEdge((int) ($data['predecessor_package_id'] ?? 0), (int) ($data['successor_package_id'] ?? 0), null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var WorkPackageDependency $current */
        $current = $this->show($record);
        $this->validateEdge(
            (int) ($data['predecessor_package_id'] ?? $current->predecessor_package_id),
            (int) ($data['successor_package_id'] ?? $current->successor_package_id),
            (int) $current->getKey(),
        );

        return parent::update($current, $data);
    }

    private function validateEdge(int $predecessor, int $successor, ?int $exceptId): void
    {
        $projects = WorkPackage::query()->whereKey([$predecessor, $successor])->pluck('project_id', 'id');

        if ($projects->count() !== 2 || $projects->unique()->count() !== 1) {
            throw SameProjectRequiredException::make();
        }

        $edges = [];
        WorkPackageDependency::query()
            ->whereIn('predecessor_package_id', WorkPackage::query()->where('project_id', $projects->first())->select('id'))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->get(['predecessor_package_id', 'successor_package_id'])
            ->each(function (WorkPackageDependency $dependency) use (&$edges): void {
                $edges[(int) $dependency->predecessor_package_id][] = (int) $dependency->successor_package_id;
            });

        $this->assertNoCycle($edges, $predecessor, $successor);
    }
}
