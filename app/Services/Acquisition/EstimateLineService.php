<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\EstimateLine;
use App\Models\Acquisition\EstimateVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Tahmin satiri servisi (10 SS3.9). Her degisiklik onayli olmayan tahmin
 * surumunde yapilir ve kok toplamlari yeniden hesaplanir.
 */
final class EstimateLineService extends AbstractService
{
    protected string $model = EstimateLine::class;

    protected string $orderBy = 'sort_order';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly EstimateVersionService $versions,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $version = EstimateVersion::query()->findOrFail((int) ($data['estimate_version_id'] ?? 0));
            $this->versions->assertEditable($version);

            $line = parent::create($data);
            $this->versions->recalculate($version);

            return $line;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var EstimateLine $current */
            $current = $this->show($record);
            $this->versions->assertEditable($current->estimateVersion);

            unset($data['estimate_version_id']);
            $line = parent::update($current, $data);
            $this->versions->recalculate($current->estimate_version_id);

            return $line;
        });
    }

    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var EstimateLine $current */
            $current = $this->show($record);
            $this->versions->assertEditable($current->estimateVersion);
            $versionId = $current->estimate_version_id;

            $deleted = parent::delete($current);
            $this->versions->recalculate($versionId);

            return $deleted;
        });
    }
}
