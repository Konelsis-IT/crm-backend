<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\Project\ProjectComponentDuplicateException;
use App\Models\Project\ProjectComponent;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ProjectComponent servisi.
 *
 * Tek ozel kural: ayni bilesen tanimi bir projeye bir kez eklenir
 * (uk_project_components_project_component). Veri tabani kisiti son
 * savunmadir; kural burada okunur bir hatayla erkenden yakalanir.
 */
final class ProjectComponentService extends AbstractService
{
    protected string $model = ProjectComponent::class;

    public function create(array $data): Model
    {
        $this->guardUniqueDefinition((int) ($data['project_id'] ?? 0), (int) ($data['component_definition_id'] ?? 0));

        return parent::create($data);
    }

    public function update(Model | int | string $record, array $data): Model
    {
        if (array_key_exists('component_definition_id', $data)) {
            $component = $record instanceof ProjectComponent ? $record : ProjectComponent::query()->findOrFail($record);

            $this->guardUniqueDefinition((int) $component->project_id, (int) $data['component_definition_id'], (int) $component->getKey());
        }

        return parent::update($record, $data);
    }

    private function guardUniqueDefinition(int $projectId, int $definitionId, ?int $exceptComponentId = null): void
    {
        if ($projectId === 0 || $definitionId === 0) {
            return;
        }

        $exists = ProjectComponent::query()
            ->where('project_id', $projectId)
            ->where('component_definition_id', $definitionId)
            ->when($exceptComponentId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptComponentId))
            ->exists();

        if ($exists) {
            throw ProjectComponentDuplicateException::make();
        }
    }
}
