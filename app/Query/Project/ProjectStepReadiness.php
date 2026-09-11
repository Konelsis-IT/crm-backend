<?php

declare(strict_types=1);

namespace App\Query\Project;

use App\Enums\Document\DocumentDiscipline;
use App\Enums\Project\BaselineStatus;
use App\Enums\Project\ExpectationKind;
use App\Enums\Project\SupplyItemKind;
use App\Enums\Project\SupplyItemStatus;
use App\Enums\Project\TeamMemberStatus;
use App\Enums\Shared\ActiveStatus;
use App\Models\Document\Document;
use App\Models\Project\FocusExpectation;
use App\Models\Project\Project;
use App\Models\Project\ProjectPhoto;
use App\Models\Project\ProjectSupplyItem;
use App\Models\Project\ProjectTeamMember;
use App\Models\Project\ProjectWorkstream;
use Illuminate\Support\Collection;

/**
 * Proje adim (odak) hazirligi: her workstream'in grubu icin tanimli
 * beklentileri (focus_expectations) projedeki gercek sayimla karsilastirir.
 * Calisma alani kontrol listesi, sekme rozetleri ve odak gecis guard'i
 * (ProjectService::changeFocus) bu siniftan okur.
 *
 * Adim dizisi:
 *   [
 *     'workstream_id', 'group_code', 'group_name', 'status' (WorkstreamStatus), 'is_current',
 *     'items' => [ ['code','name','help','kind','mandatory','min','count','met'] ],
 *     'mandatory_total', 'mandatory_met', 'optional_total', 'optional_met', 'is_ready'
 *   ]
 */
final class ProjectStepReadiness
{
    /** @var array<string, int> */
    private array $countCache = [];

    /**
     * Varsayilan siraya gore workstream'ler (grup default_sort_order).
     *
     * @return Collection<int, ProjectWorkstream>
     */
    public function orderedWorkstreams(Project $project): Collection
    {
        return $project->workstreams()
            ->with('group')
            ->get()
            ->sortBy(fn (ProjectWorkstream $ws): int => (int) ($ws->group?->default_sort_order ?? 0))
            ->values();
    }

    public function current(Project $project): ?ProjectWorkstream
    {
        if ($project->primary_focus_workstream_id === null) {
            return null;
        }

        return $this->orderedWorkstreams($project)->firstWhere('id', (int) $project->primary_focus_workstream_id);
    }

    /** Mevcut odaktan sonraki adim; son adimda null. */
    public function next(Project $project): ?ProjectWorkstream
    {
        $ordered = $this->orderedWorkstreams($project);
        $index = $ordered->search(fn (ProjectWorkstream $ws): bool => (int) $ws->getKey() === (int) $project->primary_focus_workstream_id);

        if ($index === false) {
            return $ordered->first();
        }

        return $ordered->get($index + 1);
    }

    /** Mevcut odaktan onceki adim; ilk adimda null. */
    public function previous(Project $project): ?ProjectWorkstream
    {
        $ordered = $this->orderedWorkstreams($project);
        $index = $ordered->search(fn (ProjectWorkstream $ws): bool => (int) $ws->getKey() === (int) $project->primary_focus_workstream_id);

        if ($index === false || $index === 0) {
            return null;
        }

        return $ordered->get($index - 1);
    }

    /** Hedef adim mevcut odaktan sonra mi (ileri yon)? */
    public function isForward(Project $project, int $targetWorkstreamId): bool
    {
        $ordered = $this->orderedWorkstreams($project);
        $currentIndex = $ordered->search(fn (ProjectWorkstream $ws): bool => (int) $ws->getKey() === (int) $project->primary_focus_workstream_id);
        $targetIndex = $ordered->search(fn (ProjectWorkstream $ws): bool => (int) $ws->getKey() === $targetWorkstreamId);

        if ($targetIndex === false) {
            return true;
        }

        return $currentIndex === false || $targetIndex > $currentIndex;
    }

    /**
     * Tum adimlarin hazirlik ozeti (sirali).
     *
     * @return list<array<string, mixed>>
     */
    public function forProject(Project $project): array
    {
        $this->countCache = [];
        $steps = [];

        foreach ($this->orderedWorkstreams($project) as $workstream) {
            $steps[] = $this->forWorkstream($project, $workstream);
        }

        return $steps;
    }

    /**
     * Tek adimin hazirlik ozeti.
     *
     * @return array<string, mixed>
     */
    public function forWorkstream(Project $project, ProjectWorkstream $workstream): array
    {
        $workstream->loadMissing('group');
        $group = $workstream->group;
        $locale = app()->getLocale();

        $expectations = FocusExpectation::query()
            ->where('group_definition_id', $group?->getKey() ?? 0)
            ->where('status', ActiveStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = [];
        $mandatoryTotal = 0;
        $mandatoryMet = 0;
        $optionalTotal = 0;
        $optionalMet = 0;

        foreach ($expectations as $expectation) {
            $count = $this->count($project, $expectation->kind);
            $met = $count >= (int) $expectation->min_count;

            if ($expectation->is_mandatory) {
                $mandatoryTotal++;
                $mandatoryMet += $met ? 1 : 0;
            } else {
                $optionalTotal++;
                $optionalMet += $met ? 1 : 0;
            }

            $items[] = [
                'code' => $expectation->code,
                'name' => $expectation->localizedName($locale),
                'help' => $expectation->localizedHelp($locale),
                'kind' => $expectation->kind,
                'mandatory' => (bool) $expectation->is_mandatory,
                'min' => (int) $expectation->min_count,
                'count' => $count,
                'met' => $met,
            ];
        }

        return [
            'workstream_id' => (int) $workstream->getKey(),
            'group_code' => (string) ($group?->code ?? ''),
            'group_name' => $locale === 'en' ? (string) ($group?->name_en ?? '') : (string) ($group?->name_tr ?? ''),
            'status' => $workstream->status,
            'is_current' => (int) $project->primary_focus_workstream_id === (int) $workstream->getKey(),
            'items' => $items,
            'mandatory_total' => $mandatoryTotal,
            'mandatory_met' => $mandatoryMet,
            'optional_total' => $optionalTotal,
            'optional_met' => $optionalMet,
            'is_ready' => $mandatoryMet === $mandatoryTotal,
        ];
    }

    /**
     * Mevcut odagin eksik zorunlu beklentileri (odak gecis guard'i icin).
     *
     * @return list<string>
     */
    public function missingMandatory(Project $project, ProjectWorkstream $workstream): array
    {
        $step = $this->forWorkstream($project, $workstream);

        return array_values(array_map(
            static fn (array $item): string => $item['name'],
            array_filter($step['items'], static fn (array $item): bool => $item['mandatory'] && ! $item['met']),
        ));
    }

    /** Beklenti turune gore projedeki sayim. */
    public function count(Project $project, ExpectationKind $kind): int
    {
        $cacheKey = $project->getKey().':'.$kind->value;

        if (array_key_exists($cacheKey, $this->countCache)) {
            return $this->countCache[$cacheKey];
        }

        $projectId = (int) $project->getKey();

        $count = match ($kind) {
            ExpectationKind::SiteAddress => (filled($project->site_address_line1) && filled($project->site_city)) ? 1 : 0,
            ExpectationKind::PlannedDates => ($project->planned_start_on !== null && $project->planned_finish_on !== null) ? 1 : 0,
            ExpectationKind::Components => $project->components()->count(),
            ExpectationKind::Documents => Document::query()->where('project_id', $projectId)->count(),
            ExpectationKind::Drawings => $this->documentsByDiscipline($projectId, [
                DocumentDiscipline::Electrical, DocumentDiscipline::Automation, DocumentDiscipline::Engineering, DocumentDiscipline::Civil,
            ]),
            ExpectationKind::AutomationDocuments => $this->documentsByDiscipline($projectId, [DocumentDiscipline::Automation]),
            ExpectationKind::Photos => ProjectPhoto::query()->where('project_id', $projectId)->count(),
            ExpectationKind::Wbs => $project->wbsNodes()->count(),
            ExpectationKind::Milestones => $project->milestones()->count(),
            ExpectationKind::ScheduleBaseline => $project->scheduleBaselines()->where('status', BaselineStatus::Approved->value)->count(),
            ExpectationKind::SupplyItems => ProjectSupplyItem::query()->where('project_id', $projectId)->where('status', '!=', SupplyItemStatus::Cancelled->value)->count(),
            ExpectationKind::SupplyOrdered => ProjectSupplyItem::query()->where('project_id', $projectId)->whereIn('status', array_map(fn (SupplyItemStatus $s): string => $s->value, SupplyItemStatus::orderedStates()))->count(),
            ExpectationKind::SupplyDelivered => ProjectSupplyItem::query()->where('project_id', $projectId)->whereIn('status', array_map(fn (SupplyItemStatus $s): string => $s->value, SupplyItemStatus::deliveredStates()))->count(),
            ExpectationKind::SoftwareItems => ProjectSupplyItem::query()->where('project_id', $projectId)->where('item_kind', SupplyItemKind::Software->value)->where('status', '!=', SupplyItemStatus::Cancelled->value)->count(),
            ExpectationKind::Cbs => $project->cbsNodes()->count(),
            ExpectationKind::Exposures => $project->exposures()->count(),
            ExpectationKind::TeamMembers => ProjectTeamMember::query()->where('project_id', $projectId)->where('status', TeamMemberStatus::Active->value)->count(),
            ExpectationKind::WorkPackages => $project->workPackages()->count(),
            ExpectationKind::Progress => $project->progressSnapshots()->count(),
        };

        return $this->countCache[$cacheKey] = (int) $count;
    }

    /**
     * @param  list<DocumentDiscipline>  $disciplines
     */
    private function documentsByDiscipline(int $projectId, array $disciplines): int
    {
        $values = array_map(static fn (DocumentDiscipline $d): string => $d->value, $disciplines);

        return Document::query()
            ->where('project_id', $projectId)
            ->whereHas('documentType', fn ($query) => $query->whereIn('discipline', $values))
            ->count();
    }
}
