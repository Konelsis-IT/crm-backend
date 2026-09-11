<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\HandoffStatus;
use App\Enums\Acquisition\HandoffVersionStatus;
use App\Enums\Acquisition\ReviewDecision;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\HandoffReview;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje Grubu devir incelemesi (10 SS5.4). Karar kaydi append-only'dir ve
 * yan etkisini ayni transaction'da uygular: accepted -> kabul transaction'i
 * (OperationHandoffService::accept), rejected -> surum ve devir reddedilir,
 * returned -> surum superseded, devir yeniden hazirlaniyor.
 */
final class HandoffReviewService extends AbstractService
{
    protected string $model = HandoffReview::class;

    protected string $orderBy = 'decided_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly OperationHandoffService $handoffs,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  handoff_version_id, decision, comment, project_overrides (accept icin)
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var OperationHandoffVersion $version */
            $version = OperationHandoffVersion::query()->lockForUpdate()->findOrFail((int) ($data['handoff_version_id'] ?? 0));

            if ($version->status !== HandoffVersionStatus::Submitted) {
                throw InvalidTransitionException::make(['from' => $version->status->getLabel(), 'to' => '-']);
            }

            $decision = $data['decision'] instanceof ReviewDecision ? $data['decision'] : ReviewDecision::from((string) $data['decision']);
            $overrides = (array) ($data['project_overrides'] ?? []);

            /** @var HandoffReview $review */
            $review = parent::create([
                'handoff_version_id' => $version->getKey(),
                'reviewer_personnel_id' => $data['reviewer_personnel_id'] ?? $this->actor->personnelId() ?? $version->submitted_by_personnel_id,
                'decision' => $decision,
                'comment' => $data['comment'] ?? null,
                'decided_at' => Carbon::now('UTC'),
            ]);

            match ($decision) {
                ReviewDecision::Accepted => $this->handoffs->accept($version->operation_handoff_id, (int) $version->getKey(), $overrides),
                ReviewDecision::Rejected => $this->reject($version, (string) ($data['comment'] ?? '')),
                ReviewDecision::Returned => $this->return($version, (string) ($data['comment'] ?? '')),
            };

            return $review;
        });
    }

    private function reject(OperationHandoffVersion $version, string $reason): void
    {
        $version->forceFill(['status' => HandoffVersionStatus::Rejected, 'decision_reason' => $reason])->save();
        $this->handoffs->changeStatus($version->operation_handoff_id, HandoffStatus::Rejected, $reason);
    }

    private function return(OperationHandoffVersion $version, string $reason): void
    {
        $version->forceFill(['status' => HandoffVersionStatus::Superseded, 'decision_reason' => $reason])->save();
        $this->handoffs->changeStatus($version->operation_handoff_id, HandoffStatus::Preparing, $reason);
    }
}
