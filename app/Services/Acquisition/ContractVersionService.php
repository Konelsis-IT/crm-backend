<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\ContractStatus;
use App\Enums\Acquisition\ContractVersionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\ContractVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sozlesme surumu servisi (10 SS4.2, 14 SS2.23 SM-CONTR).
 *
 * create: version_no otomatik; para birimi business case'ten. update
 * yalniz draft/review surumde. changeStatus: approved'da hash ve onaylayan,
 * executed'da executed_at + kokun current_version_id/status (ilk executed
 * surum -> signed/active) + onceki executed surum superseded; business case
 * submitted/negotiation asamasindaysa 'won' olur (SM-BC).
 */
final class ContractVersionService extends AbstractService
{
    protected string $model = ContractVersion::class;

    /** @var list<string> */
    protected array $with = ['contract', 'currency', 'approver'];

    protected string $orderBy = 'version_no';

    protected string $orderDirection = 'desc';

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
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var Contract $contract */
            $contract = Contract::query()->lockForUpdate()->findOrFail((int) ($data['contract_id'] ?? 0));
            $versionNo = (int) ContractVersion::query()->where('contract_id', $contract->getKey())->max('version_no') + 1;

            unset($data['status'], $data['version_hash'], $data['approved_by_personnel_id'], $data['approved_at'], $data['executed_at']);

            /** @var ContractVersion $version */
            $version = parent::create([
                ...$data,
                'contract_id' => $contract->getKey(),
                'version_no' => $versionNo,
                'locale' => $data['locale'] ?? 'tr',
                'currency_code' => $data['currency_code'] ?? $contract->businessCase->currency_code,
                'status' => ContractVersionStatus::Draft,
            ]);

            if ($contract->current_version_id === null) {
                $contract->forceFill(['current_version_id' => $version->getKey()])->save();
            }

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ContractVersion $current */
        $current = $this->show($record);

        if (! in_array($current->status, [ContractVersionStatus::Draft, ContractVersionStatus::Review], true)) {
            throw InvalidTransitionException::make(['from' => $current->status->getLabel(), 'to' => '-']);
        }

        unset($data['contract_id'], $data['version_no'], $data['status'], $data['version_hash'], $data['approved_by_personnel_id'], $data['approved_at'], $data['executed_at']);

        return parent::update($current, $data);
    }

    public function changeStatus(Model|int|string $record, ContractVersionStatus $target): ContractVersion
    {
        return $this->transactions->run(function () use ($record, $target): ContractVersion {
            /** @var ContractVersion $version */
            $version = $this->lockForUpdate($record);
            $from = $version->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            /** @var Contract $contract */
            $contract = Contract::query()->lockForUpdate()->findOrFail($version->contract_id);
            $attributes = ['status' => $target];

            if ($target === ContractVersionStatus::Approved) {
                $attributes['version_hash'] = hash('sha256', json_encode($version->only([
                    'contract_id', 'version_no', 'locale', 'currency_code', 'contract_value', 'summary', 'effective_from', 'effective_until',
                ]), JSON_UNESCAPED_UNICODE) ?: '');
                $attributes['approved_by_personnel_id'] = $this->actor->personnelId();
                $attributes['approved_at'] = Carbon::now('UTC');
            }

            if ($target === ContractVersionStatus::Executed) {
                $attributes['executed_at'] = Carbon::now('UTC');

                ContractVersion::query()
                    ->where('contract_id', $contract->getKey())
                    ->whereKeyNot($version->getKey())
                    ->where('status', ContractVersionStatus::Executed->value)
                    ->update(['status' => ContractVersionStatus::Superseded->value]);

                $contract->forceFill([
                    'current_version_id' => $version->getKey(),
                    'status' => ContractStatus::Active,
                    'signed_on' => $contract->signed_on ?? Carbon::now('UTC')->toDateString(),
                    'effective_from' => $contract->effective_from ?? $version->effective_from,
                ])->save();

                $case = $contract->businessCase;
                if (in_array($case->acquisition_stage, [AcquisitionStage::Submitted, AcquisitionStage::Negotiation], true)) {
                    $this->businessCases->changeStage($case, AcquisitionStage::Won);
                }
            }

            $version->forceFill($attributes)->save();
            $this->recordActivity($version, 'status_changed', ['durum' => ['onceki' => $from->value, 'yeni' => $target->value]]);

            return $version;
        });
    }
}
