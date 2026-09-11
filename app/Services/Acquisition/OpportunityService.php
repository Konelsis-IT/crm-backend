<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BidDecision;
use App\Enums\Acquisition\OpportunityStage;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\Opportunity;
use App\Models\Acquisition\OpportunityStageHistory;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Firsat servisi (10 SS2.3-2.4).
 *
 * changeStage: her asama degisikligi opportunity_stage_histories'e yazilir;
 * bid/no_bid asamalari bid kararini da doldurur; converted_to_proposal
 * business case'i offer_preparation asamasina tasir (SM-BC).
 */
final class OpportunityService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['businessCase', 'bidDecider'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly BusinessCaseService $businessCases,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['business_case_id'], $data['stage']);

        return parent::update($record, $data);
    }

    public function changeStage(Model|int|string $record, OpportunityStage $target, ?string $reason = null): Opportunity
    {
        return $this->transactions->run(function () use ($record, $target, $reason): Opportunity {
            /** @var Opportunity $opportunity */
            $opportunity = $this->lockForUpdate($record);
            $from = $opportunity->stage;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $attributes = ['stage' => $target];

            if (in_array($target, [OpportunityStage::Bid, OpportunityStage::NoBid], true)) {
                $attributes['bid_decision'] = $target === OpportunityStage::Bid ? BidDecision::Bid : BidDecision::NoBid;
                $attributes['bid_decision_by_personnel_id'] = $this->actor->personnelId();
                $attributes['bid_decision_at'] = Carbon::now('UTC');
                $attributes['bid_decision_reason'] = $reason;
            }

            if ($target === OpportunityStage::ConvertedToProposal) {
                $attributes['handoff_checklist_completed_at'] ??= Carbon::now('UTC');
            }

            $opportunity->forceFill($attributes)->save();

            OpportunityStageHistory::query()->create([
                'opportunity_id' => $opportunity->getKey(),
                'from_stage' => $from,
                'to_stage' => $target,
                'changed_by_personnel_id' => $this->actor->personnelId() ?? $opportunity->businessCase->owner_employee_id,
                'changed_at' => Carbon::now('UTC'),
                'reason' => $reason,
            ]);

            $this->recordActivity($opportunity, 'stage_changed', [
                'asama' => ['onceki' => $from->value, 'yeni' => $target->value],
            ]);

            if ($target === OpportunityStage::ConvertedToProposal
                && $opportunity->businessCase->acquisition_stage === AcquisitionStage::BusinessDevelopment) {
                $this->businessCases->changeStage($opportunity->businessCase, AcquisitionStage::OfferPreparation);
            }

            return $opportunity;
        });
    }
}
