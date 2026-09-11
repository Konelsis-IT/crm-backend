<?php

declare(strict_types=1);

namespace App\Query\Project;

use App\Models\Project\ComponentDefinition;
use App\Models\Project\OperationGroupDefinition;
use App\Models\Project\Project;
use App\Models\Project\ProjectComponent;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\WbsNode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Proje katalogu (bilesen tanimlari) secim listeleri.
 */
final class ProjectCatalogQueries
{
    /**
     * Projede zaten tanimli bilesen tanimi kimlikleri (secim listesinden
     * dislanir; duzenlemede kaydin kendisi haric tutulur).
     *
     * @return list<int>
     */
    public function usedComponentDefinitionIds(int $projectId, ?int $exceptComponentId = null): array
    {
        return ProjectComponent::query()
            ->where('project_id', $projectId)
            ->when($exceptComponentId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptComponentId))
            ->pluck('component_definition_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Bilesen / proje tipi listesi; anahtar bilesen kodu, deger Turkce ad.
     *
     * @return array<string, string>
     */
    public function componentDefinitionOptions(): array
    {
        return ComponentDefinition::query()
            ->orderBy('name_tr')
            ->pluck('name_tr', 'code')
            ->all();
    }

    /**
     * Operasyon gruplari (adimlar); anahtar id, deger Turkce ad.
     *
     * @return array<int, string>
     */
    public function operationGroupOptions(): array
    {
        return OperationGroupDefinition::query()
            ->orderBy('default_sort_order')
            ->pluck('name_tr', 'id')
            ->all();
    }

    /**
     * Proje secim listesi; anahtar id, deger "PRJ-n · ad".
     *
     * @return array<int, string>
     */
    public function projectOptions(): array
    {
        $options = [];

        $projects = Project::query()
            ->with('businessCode')
            ->orderByDesc('id')
            ->get(['id', 'name', 'project_business_code_id']);

        foreach ($projects as $project) {
            $options[(int) $project->getKey()] = trim(($project->businessCode?->formatted_code ?? '').' · '.$project->name, ' ·');
        }

        return $options;
    }

    /**
     * Projenin workstream'leri; anahtar id, deger grup adi.
     *
     * @return array<int, string>
     */
    public function workstreamOptions(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $options = [];

        $workstreams = ProjectWorkstream::query()
            ->with('group')
            ->where('project_id', $projectId)
            ->get();

        foreach ($workstreams->sortBy(fn (ProjectWorkstream $ws): int => (int) ($ws->group?->default_sort_order ?? 0)) as $workstream) {
            $options[(int) $workstream->getKey()] = (string) ($workstream->group?->name_tr ?? $workstream->getKey());
        }

        return $options;
    }

    /**
     * Projenin WBS kalemleri; anahtar id, deger "kod · ad".
     *
     * @return array<int, string>
     */
    public function wbsOptions(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $options = [];

        $nodes = WbsNode::query()
            ->where('project_id', $projectId)
            ->orderBy('wbs_code')
            ->get(['id', 'wbs_code', 'name']);

        foreach ($nodes as $node) {
            $options[(int) $node->getKey()] = $node->wbs_code.' · '.$node->name;
        }

        return $options;
    }
}
