<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\StageRequirementStatus;
use App\Models\Project\ProjectStageRequirement;
use App\Models\Project\StageWaiver;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gate muafiyeti (11 SS2.10, D-22): append-only; gereksinim duzeyinde ise
 * gereksinim 'waived' olur, risk sahibi ve telafi tarihi zorunludur.
 */
final class StageWaiverService extends AbstractService
{
    protected string $model = StageWaiver::class;

    protected string $orderBy = 'granted_at';

    protected string $orderDirection = 'desc';

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
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var StageWaiver $waiver */
            $waiver = parent::create([
                ...$data,
                'approved_by_personnel_id' => $data['approved_by_personnel_id'] ?? $this->actor->personnelId(),
                'risk_owner_personnel_id' => $data['risk_owner_personnel_id'] ?? $this->actor->personnelId(),
                'granted_at' => Carbon::now('UTC'),
            ]);

            if ($waiver->project_stage_requirement_id !== null) {
                ProjectStageRequirement::query()
                    ->whereKey($waiver->project_stage_requirement_id)
                    ->update(['status' => StageRequirementStatus::Waived->value, 'outcome_note' => $waiver->reason]);
            }

            return $waiver;
        });
    }
}
