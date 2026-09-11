<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Project\CriticalityProfile;
use App\Enums\Project\FocusDirection;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\WorkstreamStatus;
use App\Exceptions\AbstractException;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\Project;
use App\Models\Project\ProjectFocusHistory;
use App\Models\Project\ProjectWorkstream;
use App\Query\Project\ProjectStepReadiness;
use App\Services\AbstractService;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje servisi (11 SS1.1, 14 SS2.25 SM-PRJ, D-68).
 *
 * create(): kapali — proje ya devir kabulunde (ProjectOpener::open), ya
 * tekliften donusumde (ProjectConversionService) ya da createDirect() ile
 * dogrudan acilir. changeStatus SM-PRJ gecislerini; changeFocus primary
 * focus degisimini (tek acik focus history, geri yonde gerekce zorunlu,
 * ileri yonde mevcut adimin zorunlu beklentileri karsilanmis olmali veya
 * gerekceli zorlama) uygular; advanceFocus siradaki adima gecer.
 */
final class ProjectService extends AbstractService
{
    use ChecksProjectScope;

    /** @var list<string> */
    protected array $with = ['businessCase', 'businessCode', 'customerParty', 'projectManager', 'primaryFocusWorkstream'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly BusinessCaseService $businessCases,
        private readonly ProjectOpener $opener,
        private readonly ProjectStepReadiness $readiness,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        throw GuardNotSatisfiedException::make(['reason' => 'proje devir kabulu, tekliften donusum veya dogrudan olusturma (createDirect) ile acilir']);
    }

    /**
     * Dogrudan proje olusturma (D-68): gecmiste yapilmis veya suren isler.
     * Business case (TKLF'siz) + PRJ kodu + proje tek transaction'da.
     *
     * @param  array<string, mixed>  $data  name, customer_party_id, project_manager_employee_id, project_type_code, criticality_profile, status, country_code, currency_code, timezone, contract_value_snapshot, description, legacy_reference, classification_id, tarih ve adres alanlari
     */
    public function createDirect(array $data): Project
    {
        return $this->transactions->run(function () use ($data): Project {
            $profile = $data['criticality_profile'] ?? CriticalityProfile::Standard;
            $profile = $profile instanceof CriticalityProfile ? $profile : CriticalityProfile::from((string) $profile);

            [$case, $code] = $this->businessCases->createForProject([
                'title' => (string) ($data['name'] ?? ''),
                'primary_party_id' => (int) ($data['customer_party_id'] ?? 0),
                'country_code' => $data['country_code'] ?? (string) config('konelsis.legal_entity.country', 'TR'),
                'currency_code' => $data['currency_code'] ?? (string) config('konelsis.organization.default_currency', 'TRY'),
                'project_type_code' => filled($data['project_type_code'] ?? null) ? strtoupper((string) $data['project_type_code']) : null,
                'short_description' => $data['description'] ?? null,
                'criticality' => $profile === CriticalityProfile::Standard ? BusinessCriticality::Normal : BusinessCriticality::Critical,
                'estimated_value' => $data['contract_value_snapshot'] ?? null,
                'owner_employee_id' => $data['project_manager_employee_id'] ?? $this->actor->personnelId(),
                'proposal_owner_employee_id' => null,
                'classification_id' => $data['classification_id'] ?? null,
            ]);

            return $this->opener->openDirect($case, $code, [...$data, 'criticality_profile' => $profile]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset(
            $data['business_case_id'], $data['project_business_code_id'], $data['accepted_handoff_version_id'],
            $data['status'], $data['origin'], $data['primary_focus_workstream_id'], $data['current_macro_gate_code'],
            $data['customer_party_id'], $data['legal_entity_id'], $data['currency_code'], $data['contract_value_snapshot'],
            $data['cover_file_object_id'],
        );

        return parent::update($record, $data);
    }

    public function changeStatus(Model|int|string $record, ProjectStatus $target, ?string $reason = null): Project
    {
        return $this->transactions->run(function () use ($record, $target, $reason): Project {
            /** @var Project $project */
            $project = $this->lockForUpdate($record);
            $from = $project->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $attributes = ['status' => $target];
            $today = Carbon::now('UTC')->toDateString();

            if ($target === ProjectStatus::Active && $project->actual_start_on === null) {
                $attributes['actual_start_on'] = $today;
            }

            if (in_array($target, [ProjectStatus::Closed, ProjectStatus::Cancelled], true) && $project->actual_finish_on === null) {
                $attributes['actual_finish_on'] = $today;
            }

            $project->forceFill($attributes)->save();
            $this->recordActivity($project, 'status_changed', ['durum' => ['onceki' => $from->value, 'yeni' => $target->value], 'gerekce' => $reason]);

            return $project;
        });
    }

    /**
     * Primary focus degisimi: acik focus kapanir, yeni satir acilir.
     *
     * Ileri yonde mevcut adimin zorunlu beklentileri (focus_expectations)
     * karsilanmamissa gecis reddedilir; $force ile ve gerekceyle asilabilir.
     * Geri yonde gerekce zorunludur.
     */
    public function changeFocus(Model|int|string $record, int $workstreamId, FocusDirection $direction = FocusDirection::Forward, ?string $reason = null, bool $force = false): Project
    {
        return $this->transactions->run(function () use ($record, $workstreamId, $direction, $reason, $force): Project {
            /** @var Project $project */
            $project = $this->lockForUpdate($record);
            $this->assertSameProject((int) $project->getKey(), ProjectWorkstream::class, $workstreamId);

            if ((int) $project->primary_focus_workstream_id === $workstreamId) {
                return $project;
            }

            if ($direction !== FocusDirection::Initial) {
                $direction = $this->readiness->isForward($project, $workstreamId) ? FocusDirection::Forward : FocusDirection::Backward;
            }

            if ($direction === FocusDirection::Backward && blank($reason)) {
                throw GuardNotSatisfiedException::make(['reason' => 'geri yonde odak degisimi gerekce ister']);
            }

            $missing = [];
            $current = $this->readiness->current($project);

            if ($direction === FocusDirection::Forward && $current !== null) {
                $missing = $this->readiness->missingMandatory($project, $current);

                if ($missing !== [] && ! $force) {
                    throw GuardNotSatisfiedException::make(['reason' => 'mevcut adimda eksik: '.implode(', ', $missing)]);
                }

                if ($missing !== [] && blank($reason)) {
                    throw GuardNotSatisfiedException::make(['reason' => 'eksiklere ragmen ilerlemek gerekce ister']);
                }
            }

            $now = Carbon::now('UTC');

            ProjectFocusHistory::query()
                ->where('project_id', $project->getKey())
                ->whereNull('ended_at')
                ->update(['ended_at' => $now]);

            ProjectFocusHistory::query()->create([
                'project_id' => $project->getKey(),
                'workstream_id' => $workstreamId,
                'direction' => $direction,
                'started_at' => $now,
                'changed_by_personnel_id' => $this->actor->personnelId() ?? $project->project_manager_employee_id,
                'reason' => $reason,
            ]);

            $previous = $project->primary_focus_workstream_id;
            $project->forceFill(['primary_focus_workstream_id' => $workstreamId])->save();

            $this->recordActivity($project, 'focus_changed', [
                'workstream' => ['onceki' => $previous, 'yeni' => $workstreamId],
                'yon' => $direction->value,
                'gerekce' => $reason,
                'eksikler' => $missing === [] ? null : $missing,
            ]);

            return $project;
        });
    }

    /**
     * Siradaki adima gecer: mevcut workstream (istenirse) tamamlanir,
     * odak siradaki workstream'e alinir ve o workstream baslatilir.
     */
    public function advanceFocus(Model|int|string $record, ?string $reason = null, bool $force = false, bool $completeCurrent = true, bool $activateNext = true): Project
    {
        return $this->transactions->run(function () use ($record, $reason, $force, $completeCurrent, $activateNext): Project {
            /** @var Project $project */
            $project = $this->lockForUpdate($record);
            $current = $this->readiness->current($project);
            $next = $this->readiness->next($project);

            if ($next === null) {
                throw GuardNotSatisfiedException::make(['reason' => 'proje son adimda; ileri gidilecek adim yok']);
            }

            $project = $this->changeFocus($project, (int) $next->getKey(), FocusDirection::Forward, $reason, $force);

            /** @var ProjectWorkstreamService $workstreams */
            $workstreams = app(ProjectWorkstreamService::class);

            if ($completeCurrent && $current !== null) {
                $this->walkWorkstream($workstreams, $current, [
                    WorkstreamStatus::Ready->value => WorkstreamStatus::Active,
                    WorkstreamStatus::Blocked->value => WorkstreamStatus::Active,
                    WorkstreamStatus::Active->value => WorkstreamStatus::Review,
                    WorkstreamStatus::Review->value => WorkstreamStatus::Completed,
                ]);
            }

            if ($activateNext) {
                $this->walkWorkstream($workstreams, $next, [
                    WorkstreamStatus::NotReady->value => WorkstreamStatus::Ready,
                    WorkstreamStatus::Ready->value => WorkstreamStatus::Active,
                ]);
            }

            $this->recordActivity($project, 'focus_advanced', [
                'onceki_workstream' => $current?->getKey(),
                'yeni_workstream' => $next->getKey(),
                'gerekce' => $reason,
            ]);

            return $project->refresh();
        });
    }

    /**
     * Workstream'i verilen gecis haritasi boyunca yurutur; guard'a takilirsa
     * (orn. hard predecessor bekliyor) oldugu yerde birakir.
     *
     * @param  array<string, WorkstreamStatus>  $map
     */
    private function walkWorkstream(ProjectWorkstreamService $workstreams, ProjectWorkstream $workstream, array $map): void
    {
        $guard = 0;

        while ($guard++ < 6) {
            $workstream->refresh();
            $target = $map[$workstream->status->value] ?? null;

            if ($target === null || ! $workstream->status->canTransitionTo($target)) {
                return;
            }

            try {
                $workstreams->changeStatus($workstream, $target, $workstream->status === WorkstreamStatus::Blocked ? 'odak ilerletildi' : null);
            } catch (AbstractException) {
                return;
            }
        }
    }
}
