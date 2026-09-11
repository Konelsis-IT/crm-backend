<?php

declare(strict_types=1);

namespace App\Query\Notification;

use App\Filament\Resources\ContractVersions\ContractVersionResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\ProjectStageInstances\ProjectStageInstanceResource;
use App\Filament\Resources\TenderNoticeVersions\TenderNoticeVersionResource;
use App\Filament\Resources\WorkPackages\WorkPackageResource;
use App\Models\Acquisition\ContractMilestone;
use App\Models\Acquisition\ContractObligation;
use App\Models\Acquisition\TenderDeadline;
use App\Models\Personnel\PersonnelCertification;
use App\Models\Project\Project;
use App\Models\Project\ProjectIssue;
use App\Models\Project\ProjectRisk;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\ProjectStageRequirement;
use App\Models\Project\RecoveryAction;
use App\Models\Project\WorkPackage;
use App\Services\Notification\DeadlineHit;
use App\Services\Platform\SchemaReadiness;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Son tarih kaynaklari (D-82). Her kaynak, [from, until] penceresinde tarihi
 * olan ve hala bekleyen kayitlari DeadlineHit olarak doner. Kaynak eklemek
 * icin bir metot yazip SOURCES listesine eklemek yeterlidir; tetikleyici kodu
 * `deadline.<kaynak>` ve basligi `business_alert.triggers.<kod>` dil anahtaridir.
 */
final class DeadlineQueries
{
    /** @var array<string, array{method: string, batch: string}> */
    private const SOURCES = [
        'deadline.project_issue' => ['method' => 'projectIssues', 'batch' => 'B17'],
        'deadline.project_risk_review' => ['method' => 'projectRiskReviews', 'batch' => 'B17'],
        'deadline.stage_requirement' => ['method' => 'stageRequirements', 'batch' => 'B17'],
        'deadline.stage_condition' => ['method' => 'stageConditions', 'batch' => 'B17'],
        'deadline.recovery_action' => ['method' => 'recoveryActions', 'batch' => 'B17'],
        'deadline.work_package' => ['method' => 'workPackages', 'batch' => 'B17'],
        'deadline.project_finish' => ['method' => 'projectFinishes', 'batch' => 'B17'],
        'deadline.tender' => ['method' => 'tenderDeadlines', 'batch' => 'B16'],
        'deadline.contract_milestone' => ['method' => 'contractMilestones', 'batch' => 'B16'],
        'deadline.contract_obligation' => ['method' => 'contractObligations', 'batch' => 'B16'],
        'deadline.certification' => ['method' => 'certifications', 'batch' => 'B13'],
    ];

    /**
     * @return Collection<int, DeadlineHit>
     */
    public function hits(CarbonInterface $from, CarbonInterface $until): Collection
    {
        $from = CarbonImmutable::instance($from);
        $until = CarbonImmutable::instance($until);
        $hits = collect();

        foreach (self::SOURCES as $trigger => $source) {
            if (! SchemaReadiness::hasBatch($source['batch'])) {
                continue;
            }

            foreach ($this->{$source['method']}($from, $until) as $hit) {
                if ($hit instanceof DeadlineHit) {
                    $hits->push($hit);
                }
            }
        }

        return $hits;
    }

    /**
     * @return list<DeadlineHit>
     */
    private function projectIssues(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return ProjectIssue::query()
            ->with('project')
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $until])
            ->get()
            ->map(fn (ProjectIssue $issue): DeadlineHit => new DeadlineHit(
                triggerCode: 'deadline.project_issue',
                subjectType: 'project_issue',
                subjectId: (int) $issue->getKey(),
                dueAt: CarbonImmutable::instance($issue->due_at),
                params: $this->params(trim(($issue->issue_no ?? '').' '.$issue->title), $issue->project?->name),
                ownerPersonnelId: $issue->owner_personnel_id !== null ? (int) $issue->owner_personnel_id : $this->projectManagerId($issue->project),
                projectId: (int) $issue->project_id,
                url: $this->projectUrl($issue->project_id),
            ))
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function projectRiskReviews(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return ProjectRisk::query()
            ->with('project')
            ->whereIn('status', ['identified', 'assessed', 'mitigating'])
            ->whereNotNull('review_due_on')
            ->whereBetween('review_due_on', [$from->toDateString(), $until->toDateString()])
            ->get()
            ->map(fn (ProjectRisk $risk): DeadlineHit => new DeadlineHit(
                triggerCode: 'deadline.project_risk_review',
                subjectType: 'project_risk',
                subjectId: (int) $risk->getKey(),
                dueAt: CarbonImmutable::instance($risk->review_due_on),
                params: $this->params(trim(($risk->risk_no ?? '').' '.$risk->title), $risk->project?->name),
                ownerPersonnelId: $risk->owner_personnel_id !== null ? (int) $risk->owner_personnel_id : $this->projectManagerId($risk->project),
                projectId: (int) $risk->project_id,
                url: $this->projectUrl($risk->project_id),
            ))
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function stageRequirements(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return ProjectStageRequirement::query()
            ->with('stageInstance.project')
            ->whereIn('status', ['pending', 'submitted'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $until])
            ->get()
            ->map(function (ProjectStageRequirement $requirement): DeadlineHit {
                $instance = $requirement->stageInstance;
                $project = $instance?->project;
                $label = app()->getLocale() === 'en'
                    ? ($requirement->name_snapshot_en ?: $requirement->name_snapshot_tr)
                    : ($requirement->name_snapshot_tr ?: $requirement->name_snapshot_en);

                return new DeadlineHit(
                    triggerCode: 'deadline.stage_requirement',
                    subjectType: 'project_stage_requirement',
                    subjectId: (int) $requirement->getKey(),
                    dueAt: CarbonImmutable::instance($requirement->due_at),
                    params: $this->params((string) $label, $project?->name),
                    ownerPersonnelId: $requirement->owner_personnel_id !== null ? (int) $requirement->owner_personnel_id : ($instance?->owner_personnel_id !== null ? (int) $instance->owner_personnel_id : $this->projectManagerId($project)),
                    projectId: $project?->getKey() !== null ? (int) $project->getKey() : null,
                    url: $instance !== null ? $this->url(fn () => ProjectStageInstanceResource::getUrl('view', ['record' => $instance])) : null,
                );
            })
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function stageConditions(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return ProjectStageInstance::query()
            ->with(['project', 'stageNode'])
            ->where('status', 'conditionally_passed')
            ->whereNotNull('condition_due_on')
            ->whereBetween('condition_due_on', [$from->toDateString(), $until->toDateString()])
            ->get()
            ->map(fn (ProjectStageInstance $instance): DeadlineHit => new DeadlineHit(
                triggerCode: 'deadline.stage_condition',
                subjectType: 'project_stage_instance',
                subjectId: (int) $instance->getKey(),
                dueAt: CarbonImmutable::instance($instance->condition_due_on),
                params: $this->params((string) ($instance->stageNode?->name ?? $instance->stageNode?->name_tr ?? '#'.$instance->getKey()), $instance->project?->name),
                ownerPersonnelId: $instance->owner_personnel_id !== null ? (int) $instance->owner_personnel_id : $this->projectManagerId($instance->project),
                projectId: (int) $instance->project_id,
                url: $this->url(fn () => ProjectStageInstanceResource::getUrl('view', ['record' => $instance])),
            ))
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function recoveryActions(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return RecoveryAction::query()
            ->with('delayEvent.project')
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $until])
            ->get()
            ->map(function (RecoveryAction $action): DeadlineHit {
                $project = $action->delayEvent?->project;

                return new DeadlineHit(
                    triggerCode: 'deadline.recovery_action',
                    subjectType: 'recovery_action',
                    subjectId: (int) $action->getKey(),
                    dueAt: CarbonImmutable::instance($action->due_at),
                    params: $this->params(Str::limit((string) $action->description, 80), $project?->name),
                    ownerPersonnelId: $action->owner_personnel_id !== null ? (int) $action->owner_personnel_id : $this->projectManagerId($project),
                    projectId: $project?->getKey() !== null ? (int) $project->getKey() : null,
                    url: $project !== null ? $this->projectUrl($project->getKey()) : null,
                );
            })
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function workPackages(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return WorkPackage::query()
            ->with('project')
            ->whereIn('status', ['planned', 'ready', 'active'])
            ->whereNotNull('planned_finish_on')
            ->whereBetween('planned_finish_on', [$from->toDateString(), $until->toDateString()])
            ->get()
            ->map(fn (WorkPackage $package): DeadlineHit => new DeadlineHit(
                triggerCode: 'deadline.work_package',
                subjectType: 'work_package',
                subjectId: (int) $package->getKey(),
                dueAt: CarbonImmutable::instance($package->planned_finish_on),
                params: $this->params(trim(($package->package_code ?? '').' '.$package->name), $package->project?->name),
                ownerPersonnelId: $package->owner_personnel_id !== null ? (int) $package->owner_personnel_id : $this->projectManagerId($package->project),
                projectId: (int) $package->project_id,
                url: $this->url(fn () => WorkPackageResource::getUrl('view', ['record' => $package])) ?? $this->projectUrl($package->project_id),
            ))
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function projectFinishes(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return Project::query()
            ->whereIn('status', ['opening', 'active'])
            ->whereNotNull('planned_finish_on')
            ->whereNull('actual_finish_on')
            ->whereBetween('planned_finish_on', [$from->toDateString(), $until->toDateString()])
            ->get()
            ->map(fn (Project $project): DeadlineHit => new DeadlineHit(
                triggerCode: 'deadline.project_finish',
                subjectType: 'project',
                subjectId: (int) $project->getKey(),
                dueAt: CarbonImmutable::instance($project->planned_finish_on),
                params: $this->params((string) $project->name, null),
                ownerPersonnelId: $this->projectManagerId($project),
                projectId: (int) $project->getKey(),
                url: $this->projectUrl($project->getKey()),
            ))
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function tenderDeadlines(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return TenderDeadline::query()
            ->with('version.notice.businessCase')
            ->whereNotNull('due_at_utc')
            ->whereBetween('due_at_utc', [$from, $until])
            ->whereHas('version', fn ($query) => $query->where('status', 'current'))
            ->get()
            ->map(function (TenderDeadline $deadline): DeadlineHit {
                $version = $deadline->version;
                $notice = $version?->notice;
                $businessCase = $notice?->businessCase;
                $type = $deadline->deadline_type;
                $typeLabel = is_object($type) && method_exists($type, 'getLabel') ? $type->getLabel() : (string) $type;

                return new DeadlineHit(
                    triggerCode: 'deadline.tender',
                    subjectType: 'tender_deadline',
                    subjectId: (int) $deadline->getKey(),
                    dueAt: CarbonImmutable::instance($deadline->due_at_utc),
                    params: $this->params(trim($typeLabel.' — '.($notice?->title ?? '')), $businessCase?->title),
                    ownerPersonnelId: $businessCase?->owner_employee_id !== null ? (int) $businessCase->owner_employee_id : null,
                    url: $version !== null ? $this->url(fn () => TenderNoticeVersionResource::getUrl('view', ['record' => $version])) : null,
                );
            })
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function contractMilestones(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return ContractMilestone::query()
            ->with('version.contract.businessCase')
            ->whereNotNull('planned_on')
            ->whereBetween('planned_on', [$from->toDateString(), $until->toDateString()])
            ->whereHas('version', fn ($query) => $query->whereIn('status', ['approved', 'executed']))
            ->get()
            ->map(function (ContractMilestone $milestone): DeadlineHit {
                $version = $milestone->version;
                $businessCase = $version?->contract?->businessCase;

                return new DeadlineHit(
                    triggerCode: 'deadline.contract_milestone',
                    subjectType: 'contract_milestone',
                    subjectId: (int) $milestone->getKey(),
                    dueAt: CarbonImmutable::instance($milestone->planned_on),
                    params: $this->params(trim(($milestone->milestone_code ?? '').' '.$milestone->name), $businessCase?->title),
                    ownerPersonnelId: $businessCase?->owner_employee_id !== null ? (int) $businessCase->owner_employee_id : null,
                    url: $version !== null ? $this->url(fn () => ContractVersionResource::getUrl('view', ['record' => $version])) : null,
                );
            })
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function contractObligations(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return ContractObligation::query()
            ->with('version.contract.businessCase')
            ->where('status', 'open')
            ->whereNotNull('due_on')
            ->whereBetween('due_on', [$from->toDateString(), $until->toDateString()])
            ->get()
            ->map(function (ContractObligation $obligation): DeadlineHit {
                $version = $obligation->version;
                $businessCase = $version?->contract?->businessCase;

                return new DeadlineHit(
                    triggerCode: 'deadline.contract_obligation',
                    subjectType: 'contract_obligation',
                    subjectId: (int) $obligation->getKey(),
                    dueAt: CarbonImmutable::instance($obligation->due_on),
                    params: $this->params(trim(($obligation->obligation_code ?? '').' '.Str::limit((string) $obligation->description, 80)), $businessCase?->title),
                    ownerPersonnelId: $businessCase?->owner_employee_id !== null ? (int) $businessCase->owner_employee_id : null,
                    url: $version !== null ? $this->url(fn () => ContractVersionResource::getUrl('view', ['record' => $version])) : null,
                );
            })
            ->all();
    }

    /**
     * @return list<DeadlineHit>
     */
    private function certifications(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return PersonnelCertification::query()
            ->with(['personnel', 'certification'])
            ->whereIn('status', ['valid', 'expiring'])
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [$from->toDateString(), $until->toDateString()])
            ->get()
            ->map(fn (PersonnelCertification $record): DeadlineHit => new DeadlineHit(
                triggerCode: 'deadline.certification',
                subjectType: 'personnel_certification',
                subjectId: (int) $record->getKey(),
                dueAt: CarbonImmutable::instance($record->valid_until),
                params: $this->params(trim(($record->certification?->name ?? '').' '.($record->certificate_no ?? '')), $record->personnel?->full_name),
                ownerPersonnelId: (int) $record->personnel_id,
                orgUnitId: $record->personnel?->org_unit_id !== null ? (int) $record->personnel->org_unit_id : null,
                url: $record->personnel !== null ? $this->url(fn () => PersonnelResource::getUrl('view', ['record' => $record->personnel])) : null,
            ))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function params(string $subject, ?string $context): array
    {
        return [
            'subject' => trim($subject) !== '' ? trim($subject) : '-',
            'context' => filled($context) ? (string) $context : '-',
        ];
    }

    private function projectManagerId(?Model $project): ?int
    {
        $id = $project?->getAttribute('project_manager_employee_id');

        return $id !== null ? (int) $id : null;
    }

    private function projectUrl(int|string|null $projectId): ?string
    {
        if ($projectId === null) {
            return null;
        }

        return $this->url(fn () => ProjectResource::getUrl('view', ['record' => $projectId]));
    }

    private function url(callable $resolver): ?string
    {
        try {
            return (string) $resolver();
        } catch (Throwable) {
            return null;
        }
    }
}
