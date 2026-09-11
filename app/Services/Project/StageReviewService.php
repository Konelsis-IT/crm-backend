<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\StageInstanceStatus;
use App\Enums\Project\StageReviewDecision;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\StageEvidence;
use App\Models\Project\StageReview;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gate inceleme karari (11 SS2.9). Append-only kayit; kanit setinin hash'i
 * karara muhurlenir ve gate instance'i ayni transaction'da karara gore
 * gecer (SM-GATE). ready_for_review'daki gate once approval_pending'e alinir.
 */
final class StageReviewService extends AbstractService
{
    protected string $model = StageReview::class;

    protected string $orderBy = 'decided_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly ProjectStageInstanceService $instances,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  project_stage_instance_id, decision, conditions, comment, condition_due_on
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var ProjectStageInstance $instance */
            $instance = ProjectStageInstance::query()->lockForUpdate()->findOrFail((int) ($data['project_stage_instance_id'] ?? 0));

            if ($instance->status === StageInstanceStatus::ReadyForReview) {
                $instance = $this->instances->changeStatus($instance, StageInstanceStatus::ApprovalPending);
            }

            if ($instance->status !== StageInstanceStatus::ApprovalPending) {
                throw GuardNotSatisfiedException::make(['reason' => 'gate onay bekleme durumunda degil']);
            }

            $decision = $data['decision'] instanceof StageReviewDecision ? $data['decision'] : StageReviewDecision::from((string) $data['decision']);

            if ($decision === StageReviewDecision::ConditionallyPassed && blank($data['conditions'] ?? null)) {
                throw GuardNotSatisfiedException::make(['reason' => 'sartli gecis kosul metni ister']);
            }

            $evidenceHashes = StageEvidence::query()
                ->whereIn('project_stage_requirement_id', $instance->requirements()->select('id'))
                ->orderBy('id')
                ->pluck('evidence_hash')
                ->all();

            /** @var StageReview $review */
            $review = parent::create([
                'project_stage_instance_id' => $instance->getKey(),
                'reviewer_personnel_id' => $data['reviewer_personnel_id'] ?? $this->actor->personnelId() ?? $instance->owner_personnel_id,
                'decision' => $decision,
                'reviewed_hash' => hash('sha256', implode('|', $evidenceHashes)),
                'conditions' => $data['conditions'] ?? null,
                'comment' => $data['comment'] ?? null,
                'decided_at' => Carbon::now('UTC'),
            ]);

            $target = match ($decision) {
                StageReviewDecision::Passed => StageInstanceStatus::Passed,
                StageReviewDecision::ConditionallyPassed => StageInstanceStatus::ConditionallyPassed,
                StageReviewDecision::Rejected => StageInstanceStatus::Rejected,
            };

            $this->instances->changeStatus($instance, $target, [
                'via_review' => true,
                'condition_due_on' => $data['condition_due_on'] ?? null,
                'reason' => $data['comment'] ?? null,
            ]);

            return $review;
        });
    }
}
