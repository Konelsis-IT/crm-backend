<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Project\CriticalityProfile;
use App\Enums\Project\DependencyType;
use App\Enums\Project\FocusDirection;
use App\Enums\Project\ProjectOrigin;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\StageInstanceStatus;
use App\Enums\Project\StageRequirementStatus;
use App\Enums\Project\StageTemplateProjectType;
use App\Enums\Project\StageTemplateVersionStatus;
use App\Enums\Project\WorkstreamStatus;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\Acquisition\HandoffNotAcceptableException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCode;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Project\ComponentDefinition;
use App\Models\Project\OperationGroupDefinition;
use App\Models\Project\Project;
use App\Models\Project\ProjectComponent;
use App\Models\Project\ProjectFocusHistory;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\ProjectStageRequirement;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\StageTemplateVersion;
use App\Models\Project\WorkstreamDependency;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\TransactionRunner;
use Illuminate\Support\Carbon;

/**
 * Proje kartini acar (14 SS2.24, 11 SS1, D-68).
 *
 * open(): Operasyona devir kabul transaction'i icinden (OperationHandoffService::accept).
 * openDirect(): dogrudan olusturma (gecmis/aktif projelerin sisteme alinmasi);
 * ProjectService::createDirect() transaction'i icinden.
 *
 * Her iki yolda da: proje karti, alti workstream (varsayilan sira ve FS hard
 * bagimliliklari, D-22), ilk primary focus, sablon surumunden stage-gate
 * instance'lari ve requirement snapshot'lari, proje tipine gore bilesen.
 */
final class ProjectOpener
{
    /** Girdi dizisinden dogrudan projeye kopyalanan serbest alanlar. */
    private const OPTIONAL_INPUT_KEYS = [
        'site_location', 'planned_start_on', 'planned_finish_on', 'description', 'legacy_reference',
        'site_address_line1', 'site_address_line2', 'site_district', 'site_city', 'site_postal_code',
        'site_country_code', 'site_latitude', 'site_longitude', 'site_note',
    ];

    public function __construct(
        private readonly ActorContext $actor,
        private readonly ActivityRecorder $activities,
        private readonly TransactionRunner $transactions,
    ) {}

    /**
     * Devir kabulunde proje acar.
     *
     * @param  array<string, mixed>  $overrides  name, project_manager_employee_id, stage_template_version_id, origin, site_location, planned_start_on, planned_finish_on, adres alanlari
     */
    public function open(BusinessCase $case, OperationHandoffVersion $version, BusinessCode $projectCode, array $overrides = []): Project
    {
        $this->transactions->assertInTransaction('ProjectOpener::open');

        $templateVersion = $this->resolveTemplateVersion($case, $overrides['stage_template_version_id'] ?? null);
        $managerId = (int) ($overrides['project_manager_employee_id'] ?? $case->owner_employee_id);
        $origin = $overrides['origin'] ?? ProjectOrigin::Handoff;
        $origin = $origin instanceof ProjectOrigin ? $origin : ProjectOrigin::from((string) $origin);

        $project = $this->openProject($case, $projectCode, $templateVersion, $managerId, [
            'accepted_handoff_version_id' => $version->getKey(),
            'origin' => $origin,
            'contract_value_snapshot' => $version->contractVersion?->contract_value ?? $version->proposalVersion?->total_price,
            'status' => ProjectStatus::Opening,
        ], $overrides);

        $this->activities->record(new ActivityInput(
            subjectType: 'project',
            subjectId: (int) $project->getKey(),
            actionCode: 'project.opened',
            changes: ['kod' => $projectCode->formatted_code, 'business_case_id' => $case->getKey(), 'kaynak' => $origin->value],
        ));

        return $project->refresh();
    }

    /**
     * Dogrudan proje acar (D-68). Gecmiste bitmis veya suren isler icin
     * baslangic durumu ve fiili tarihler girdiden alinir.
     *
     * @param  array<string, mixed>  $data  name, project_manager_employee_id, status, stage_template_version_id, criticality_profile, timezone, contract_value_snapshot, actual_start_on, actual_finish_on, adres alanlari
     */
    public function openDirect(BusinessCase $case, BusinessCode $projectCode, array $data = []): Project
    {
        $this->transactions->assertInTransaction('ProjectOpener::openDirect');

        $templateVersion = $this->resolveTemplateVersion($case, $data['stage_template_version_id'] ?? null);
        $managerId = (int) ($data['project_manager_employee_id'] ?? $case->owner_employee_id ?? $this->actor->personnelId());

        $status = $data['status'] ?? ProjectStatus::Opening;
        $status = $status instanceof ProjectStatus ? $status : ProjectStatus::from((string) $status);

        $specific = [
            'accepted_handoff_version_id' => null,
            'origin' => ProjectOrigin::Direct,
            'contract_value_snapshot' => $data['contract_value_snapshot'] ?? null,
            'status' => $status,
            'actual_start_on' => $data['actual_start_on'] ?? null,
            'actual_finish_on' => $data['actual_finish_on'] ?? null,
        ];

        if (array_key_exists('criticality_profile', $data) && filled($data['criticality_profile'])) {
            $profile = $data['criticality_profile'];
            $specific['criticality_profile'] = $profile instanceof CriticalityProfile ? $profile : CriticalityProfile::from((string) $profile);
        }

        if (filled($data['timezone'] ?? null)) {
            $specific['timezone'] = (string) $data['timezone'];
        }

        $project = $this->openProject($case, $projectCode, $templateVersion, $managerId, $specific, $data);

        // Acilis disinda bir durumla (suren/bitmis is) alinan projede ilk adim fiilen baslamis sayilir.
        if ($status !== ProjectStatus::Opening) {
            ProjectWorkstream::query()
                ->whereKey($project->primary_focus_workstream_id)
                ->update([
                    'status' => WorkstreamStatus::Active->value,
                    'actual_start_on' => $data['actual_start_on'] ?? Carbon::now('UTC')->toDateString(),
                ]);
        }

        $this->activities->record(new ActivityInput(
            subjectType: 'project',
            subjectId: (int) $project->getKey(),
            actionCode: 'project.created_direct',
            changes: ['kod' => $projectCode->formatted_code, 'business_case_id' => $case->getKey(), 'durum' => $status->value, 'eski_referans' => $data['legacy_reference'] ?? null],
        ));

        return $project->refresh();
    }

    /**
     * @param  array<string, mixed>  $specific  yola ozgu kolonlar (devir surumu, kaynak, durum...)
     * @param  array<string, mixed>  $input  kullanici girdisi (ad, tarih, adres...)
     */
    private function openProject(BusinessCase $case, BusinessCode $projectCode, StageTemplateVersion $templateVersion, int $managerId, array $specific, array $input): Project
    {
        $now = Carbon::now('UTC');

        $optional = array_intersect_key($input, array_flip(self::OPTIONAL_INPUT_KEYS));

        $project = Project::query()->create([
            'business_case_id' => $case->getKey(),
            'project_business_code_id' => $projectCode->getKey(),
            'name' => filled($input['name'] ?? null) ? $input['name'] : $case->title,
            'customer_party_id' => $case->primary_party_id,
            'legal_entity_id' => $case->legal_entity_id,
            'project_manager_employee_id' => $managerId,
            'country_code' => $case->country_code,
            'timezone' => (string) config('konelsis.organization.default_timezone', 'Europe/Istanbul'),
            'currency_code' => $case->currency_code,
            'stage_template_version_id' => $templateVersion->getKey(),
            'criticality_profile' => $case->criticality === BusinessCriticality::Critical ? CriticalityProfile::Critical : CriticalityProfile::Standard,
            'classification_id' => $case->classification_id,
            'site_country_code' => $optional['site_country_code'] ?? $case->country_code,
            ...$optional,
            ...$specific,
        ]);

        $workstreams = $this->openWorkstreams($project, $managerId);
        $this->openFocus($project, $workstreams, $now);
        $this->openStages($project, $templateVersion, $managerId);
        $this->openComponents($project, $case);

        return $project;
    }

    private function resolveTemplateVersion(BusinessCase $case, int|string|null $requested): StageTemplateVersion
    {
        if ($requested !== null && $requested !== '') {
            return StageTemplateVersion::query()->findOrFail((int) $requested);
        }

        $typeCode = strtolower((string) $case->project_type_code);
        $types = in_array($typeCode, StageTemplateProjectType::values(), true)
            ? [$typeCode, StageTemplateProjectType::Generic->value]
            : [StageTemplateProjectType::Generic->value];

        foreach ($types as $type) {
            $version = StageTemplateVersion::query()
                ->where('status', StageTemplateVersionStatus::Published->value)
                ->whereHas('template', fn ($query) => $query->where('project_type', $type))
                ->orderByDesc('published_at')
                ->first();

            if ($version !== null) {
                return $version;
            }
        }

        throw HandoffNotAcceptableException::make(['reason' => 'yayimlanmis bir stage-gate sablonu yok']);
    }

    /**
     * @return list<ProjectWorkstream>
     */
    private function openWorkstreams(Project $project, int $managerId): array
    {
        $groups = OperationGroupDefinition::query()
            ->where('status', ActiveStatus::Active->value)
            ->orderBy('default_sort_order')
            ->get();

        if ($groups->isEmpty()) {
            throw HandoffNotAcceptableException::make(['reason' => 'operasyon grubu katalogu bos']);
        }

        $workstreams = [];
        $previous = null;

        foreach ($groups as $index => $group) {
            $workstream = ProjectWorkstream::query()->create([
                'project_id' => $project->getKey(),
                'group_definition_id' => $group->getKey(),
                'owner_personnel_id' => $managerId,
                'status' => $index === 0 ? WorkstreamStatus::Ready : WorkstreamStatus::NotReady,
                'progress_pct' => 0,
            ]);

            if ($previous !== null) {
                WorkstreamDependency::query()->create([
                    'predecessor_workstream_id' => $previous->getKey(),
                    'successor_workstream_id' => $workstream->getKey(),
                    'dependency_type' => DependencyType::FS,
                    'lag_days' => 0,
                    'is_hard' => true,
                ]);
            }

            $workstreams[] = $workstream;
            $previous = $workstream;
        }

        return $workstreams;
    }

    /**
     * @param  list<ProjectWorkstream>  $workstreams
     */
    private function openFocus(Project $project, array $workstreams, Carbon $now): void
    {
        $first = $workstreams[0];

        ProjectFocusHistory::query()->create([
            'project_id' => $project->getKey(),
            'workstream_id' => $first->getKey(),
            'direction' => FocusDirection::Initial,
            'started_at' => $now,
            'changed_by_personnel_id' => $this->actor->personnelId() ?? $project->project_manager_employee_id,
        ]);

        $project->forceFill(['primary_focus_workstream_id' => $first->getKey()])->save();
    }

    private function openStages(Project $project, StageTemplateVersion $templateVersion, int $managerId): void
    {
        $nodes = $templateVersion->nodes()->with('requirementDefinitions')->orderBy('sequence_no')->get();

        foreach ($nodes as $node) {
            $instance = ProjectStageInstance::query()->create([
                'project_id' => $project->getKey(),
                'stage_node_id' => $node->getKey(),
                'owner_personnel_id' => $managerId,
                'status' => StageInstanceStatus::NotStarted,
            ]);

            foreach ($node->requirementDefinitions as $definition) {
                ProjectStageRequirement::query()->create([
                    'project_stage_instance_id' => $instance->getKey(),
                    'requirement_definition_id' => $definition->getKey(),
                    'requirement_code_snapshot' => $definition->requirement_code,
                    'name_snapshot_tr' => $definition->name_tr,
                    'name_snapshot_en' => $definition->name_en,
                    'evidence_type_snapshot' => $definition->evidence_type,
                    'is_mandatory_snapshot' => $definition->is_mandatory,
                    'status' => StageRequirementStatus::Pending,
                ]);
            }
        }
    }

    private function openComponents(Project $project, BusinessCase $case): void
    {
        $code = strtoupper((string) $case->project_type_code);

        if ($code === '') {
            return;
        }

        $definition = ComponentDefinition::query()->where('code', $code)->first();

        if ($definition === null) {
            return;
        }

        ProjectComponent::query()->create([
            'project_id' => $project->getKey(),
            'component_definition_id' => $definition->getKey(),
            'scope_state' => 'in_scope',
        ]);
    }
}
