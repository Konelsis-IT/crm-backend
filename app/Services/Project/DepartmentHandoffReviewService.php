<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\HandoffStatus;
use App\Enums\Acquisition\HandoffVersionStatus;
use App\Enums\Acquisition\ReviewDecision;
use App\Enums\Project\WorkstreamStatus;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\DepartmentHandoff;
use App\Models\Project\DepartmentHandoffReview;
use App\Models\Project\DepartmentHandoffVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Departman devri inceleme karari (11 SS3.4, SM-DH). accepted: surum ve
 * devir kabul edilir, hedef workstream hazir yapilir (hard bagimliliklari
 * bekliyorsa not_ready kalir); rejected: surum/devir reddedilir; returned:
 * surum superseded, devir yeniden hazirlaniyor.
 */
final class DepartmentHandoffReviewService extends AbstractService
{
    protected string $model = DepartmentHandoffReview::class;

    protected string $orderBy = 'decided_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly DepartmentHandoffService $handoffs,
        private readonly ProjectWorkstreamService $workstreams,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var DepartmentHandoffVersion $version */
            $version = DepartmentHandoffVersion::query()->lockForUpdate()->findOrFail((int) ($data['handoff_version_id'] ?? 0));

            if ($version->status !== HandoffVersionStatus::Submitted) {
                throw InvalidTransitionException::make(['from' => $version->status->getLabel(), 'to' => '-']);
            }

            $decision = $data['decision'] instanceof ReviewDecision ? $data['decision'] : ReviewDecision::from((string) $data['decision']);

            /** @var DepartmentHandoffReview $review */
            $review = parent::create([
                'handoff_version_id' => $version->getKey(),
                'reviewer_personnel_id' => $data['reviewer_personnel_id'] ?? $this->actor->personnelId() ?? $version->submitted_by_personnel_id,
                'decision' => $decision,
                'comment' => $data['comment'] ?? null,
                'decided_at' => Carbon::now('UTC'),
            ]);

            /** @var DepartmentHandoff $handoff */
            $handoff = DepartmentHandoff::query()->lockForUpdate()->findOrFail($version->department_handoff_id);

            match ($decision) {
                ReviewDecision::Accepted => $this->accept($handoff, $version),
                ReviewDecision::Rejected => $this->reject($handoff, $version, (string) ($data['comment'] ?? '')),
                ReviewDecision::Returned => $this->return($handoff, $version, (string) ($data['comment'] ?? '')),
            };

            return $review;
        });
    }

    private function accept(DepartmentHandoff $handoff, DepartmentHandoffVersion $version): void
    {
        if ($handoff->status !== HandoffStatus::InReview) {
            throw InvalidTransitionException::make(['from' => $handoff->status->getLabel(), 'to' => HandoffStatus::Accepted->getLabel()]);
        }

        $version->forceFill(['status' => HandoffVersionStatus::Accepted])->save();
        $handoff->forceFill([
            'status' => HandoffStatus::Accepted,
            'accepted_version_id' => $version->getKey(),
            'accepted_by_personnel_id' => $this->actor->personnelId(),
            'accepted_at' => Carbon::now('UTC'),
        ])->save();

        $this->recordActivity($handoff, 'accepted', ['surum' => $version->version_no]);

        $target = $handoff->targetWorkstream;

        if ($target !== null && $target->status === WorkstreamStatus::NotReady) {
            try {
                $this->workstreams->changeStatus($target, WorkstreamStatus::Ready);
            } catch (GuardNotSatisfiedException) {
                // Hard bagimliliklar tamamlaninca ProjectWorkstreamService successor'i hazir yapar.
            }
        }
    }

    private function reject(DepartmentHandoff $handoff, DepartmentHandoffVersion $version, string $reason): void
    {
        $version->forceFill(['status' => HandoffVersionStatus::Rejected])->save();
        $this->handoffs->changeStatus($handoff, HandoffStatus::Rejected, $reason);
    }

    private function return(DepartmentHandoff $handoff, DepartmentHandoffVersion $version, string $reason): void
    {
        $version->forceFill(['status' => HandoffVersionStatus::Superseded])->save();
        $this->handoffs->changeStatus($handoff, HandoffStatus::Preparing, $reason);
    }
}
