<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\Applicability;
use App\Enums\Project\StageInstanceStatus;
use App\Enums\Project\StageRequirementStatus;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\Project;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\StageDependency;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stage-gate instance servisi (11 SS2.6, 14 SS2.27 SM-GATE).
 *
 * Gate durumu formdan duzenlenmez: preparing icin hard predecessor gate'ler
 * gecilmis olmali; ready_for_review icin uygulanabilir zorunlu
 * requirement'lar accepted/waived olmali; passed/conditionally_passed/
 * rejected yalniz StageReviewService karariyla yazilir; reopened gerekce
 * ister. Onay motoru (B07) gelene kadar approval_pending elle gecilir.
 */
final class ProjectStageInstanceService extends AbstractService
{
    protected string $model = ProjectStageInstance::class;

    /** @var list<string> */
    protected array $with = ['stageNode', 'owner'];

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
    public function update(Model|int|string $record, array $data): Model
    {
        return parent::update($record, array_intersect_key($data, array_flip(['owner_personnel_id', 'condition_due_on', 'row_version'])));
    }

    /**
     * @param  array<string, mixed>  $context  reason, condition_due_on, via_review
     */
    public function changeStatus(Model|int|string $record, StageInstanceStatus $target, array $context = []): ProjectStageInstance
    {
        return $this->transactions->run(function () use ($record, $target, $context): ProjectStageInstance {
            /** @var ProjectStageInstance $instance */
            $instance = $this->lockForUpdate($record);
            $from = $instance->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $decisionStates = [StageInstanceStatus::Passed, StageInstanceStatus::ConditionallyPassed, StageInstanceStatus::Rejected];

            if (in_array($target, $decisionStates, true) && ! (bool) ($context['via_review'] ?? false)) {
                throw GuardNotSatisfiedException::make(['reason' => 'gate karari yalniz inceleme kaydiyla verilir']);
            }

            $now = Carbon::now('UTC');
            $attributes = ['status' => $target];

            switch ($target) {
                case StageInstanceStatus::Preparing:
                    $this->assertPredecessorGatesPassed($instance);
                    $attributes['entered_at'] ??= $instance->entered_at ?? $now;
                    break;
                case StageInstanceStatus::ReadyForReview:
                    $this->assertRequirementsSatisfied($instance);
                    $attributes['ready_at'] = $now;
                    break;
                case StageInstanceStatus::Passed:
                    $attributes['passed_at'] = $now;
                    break;
                case StageInstanceStatus::ConditionallyPassed:
                    if (blank($context['condition_due_on'] ?? null)) {
                        throw GuardNotSatisfiedException::make(['reason' => 'sartli gecis telafi tarihi ister']);
                    }
                    $attributes['passed_at'] = $now;
                    $attributes['condition_due_on'] = $context['condition_due_on'];
                    break;
                case StageInstanceStatus::Reopened:
                    if (blank($context['reason'] ?? null)) {
                        throw GuardNotSatisfiedException::make(['reason' => 'gate yeniden acma gerekce ister']);
                    }
                    break;
                default:
                    break;
            }

            $instance->forceFill($attributes)->save();

            if (in_array($target, [StageInstanceStatus::Passed, StageInstanceStatus::ConditionallyPassed], true)) {
                Project::query()->whereKey($instance->project_id)->update(['current_macro_gate_code' => $instance->stageNode->stage_code]);
            }

            $this->recordActivity($instance, 'status_changed', [
                'gate' => $instance->stageNode->stage_code,
                'durum' => ['onceki' => $from->value, 'yeni' => $target->value],
                'gerekce' => $context['reason'] ?? null,
            ]);

            return $instance;
        });
    }

    private function assertPredecessorGatesPassed(ProjectStageInstance $instance): void
    {
        $predecessorNodeIds = StageDependency::query()
            ->where('successor_node_id', $instance->stage_node_id)
            ->where('is_hard', true)
            ->pluck('predecessor_node_id');

        if ($predecessorNodeIds->isEmpty()) {
            return;
        }

        $open = ProjectStageInstance::query()
            ->where('project_id', $instance->project_id)
            ->whereIn('stage_node_id', $predecessorNodeIds)
            ->whereNotIn('status', [StageInstanceStatus::Passed->value, StageInstanceStatus::ConditionallyPassed->value])
            ->count();

        if ($open > 0) {
            throw GuardNotSatisfiedException::make(['reason' => "{$open} onceki gate henuz gecilmedi"]);
        }
    }

    private function assertRequirementsSatisfied(ProjectStageInstance $instance): void
    {
        $open = $instance->requirements()
            ->where('applicability', Applicability::Applicable->value)
            ->where('is_mandatory_snapshot', true)
            ->whereNotIn('status', [StageRequirementStatus::Accepted->value, StageRequirementStatus::Waived->value])
            ->count();

        if ($open > 0) {
            throw GuardNotSatisfiedException::make(['reason' => "{$open} zorunlu gate gereksinimi kabul edilmedi"]);
        }
    }
}
