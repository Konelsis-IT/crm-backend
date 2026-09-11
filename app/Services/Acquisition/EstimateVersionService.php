<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\EstimateVersionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\EstimateVersion;
use App\Models\Acquisition\ProposalVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Maliyet tahmini surumu servisi (10 SS3.8).
 *
 * create: version_no teklif surumu basina otomatik; hazirlayan aktor.
 * recalculate: satirlardan toplam maliyet/fiyat hesaplanir (EstimateLineService
 * her satir degisikliginde cagirir). approve: onaylayan/tarih yazilir, ayni
 * teklif surumundeki onceki onayli tahmin superseded olur.
 */
final class EstimateVersionService extends AbstractService
{
    protected string $model = EstimateVersion::class;

    /** @var list<string> */
    protected array $with = ['currency', 'preparer', 'approver'];

    protected string $orderBy = 'version_no';

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
            /** @var ProposalVersion $proposalVersion */
            $proposalVersion = ProposalVersion::query()->findOrFail((int) ($data['proposal_version_id'] ?? 0));
            $versionNo = (int) EstimateVersion::query()->where('proposal_version_id', $proposalVersion->getKey())->max('version_no') + 1;

            unset($data['status'], $data['approved_by_personnel_id'], $data['approved_at'], $data['total_cost'], $data['total_price']);

            return parent::create([
                ...$data,
                'version_no' => $versionNo,
                'currency_code' => $data['currency_code'] ?? $proposalVersion->currency_code,
                'status' => EstimateVersionStatus::Draft,
                'prepared_by_personnel_id' => $this->actor->personnelId() ?? $proposalVersion->prepared_by_personnel_id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var EstimateVersion $current */
        $current = $this->show($record);
        $this->assertEditable($current);

        unset($data['proposal_version_id'], $data['version_no'], $data['status'], $data['approved_by_personnel_id'], $data['approved_at'], $data['total_cost'], $data['total_price']);

        return parent::update($current, $data);
    }

    public function assertEditable(EstimateVersion $version): void
    {
        if (! in_array($version->status, [EstimateVersionStatus::Draft, EstimateVersionStatus::Reviewed], true)) {
            throw InvalidTransitionException::make(['from' => $version->status->getLabel(), 'to' => '-']);
        }
    }

    /** Satir toplamlarini koke yazar. */
    public function recalculate(Model|int|string $record): EstimateVersion
    {
        /** @var EstimateVersion $version */
        $version = $this->show($record);

        $totals = $version->lines()
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) AS cost, COALESCE(SUM(quantity * COALESCE(unit_price, 0)), 0) AS price')
            ->first();

        $version->forceFill([
            'total_cost' => (string) ($totals?->cost ?? 0),
            'total_price' => (string) ($totals?->price ?? 0),
        ])->save();

        return $version;
    }

    public function changeStatus(Model|int|string $record, EstimateVersionStatus $target): EstimateVersion
    {
        return $this->transactions->run(function () use ($record, $target): EstimateVersion {
            /** @var EstimateVersion $version */
            $version = $this->lockForUpdate($record);
            $from = $version->status;

            $allowed = match ($from) {
                EstimateVersionStatus::Draft => [EstimateVersionStatus::Reviewed],
                EstimateVersionStatus::Reviewed => [EstimateVersionStatus::Approved, EstimateVersionStatus::Draft],
                EstimateVersionStatus::Approved => [EstimateVersionStatus::Superseded],
                EstimateVersionStatus::Superseded => [],
            };

            if (! in_array($target, $allowed, true)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $attributes = ['status' => $target];

            if ($target === EstimateVersionStatus::Approved) {
                $attributes['approved_by_personnel_id'] = $this->actor->personnelId();
                $attributes['approved_at'] = Carbon::now('UTC');

                EstimateVersion::query()
                    ->where('proposal_version_id', $version->proposal_version_id)
                    ->whereKeyNot($version->getKey())
                    ->where('status', EstimateVersionStatus::Approved->value)
                    ->update(['status' => EstimateVersionStatus::Superseded->value]);
            }

            $version->forceFill($attributes)->save();
            $this->recordActivity($version, 'status_changed', ['durum' => ['onceki' => $from->value, 'yeni' => $target->value]]);

            return $version;
        });
    }
}
