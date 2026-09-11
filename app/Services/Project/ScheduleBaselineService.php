<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\BaselineStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\Project;
use App\Models\Project\ScheduleBaseline;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Takvim baseline'i servisi (11 SS3.8): version_no otomatik; approve
 * onceki onayli baseline'i superseded yapar ve proje planlanan
 * tarihlerini baseline'dan gunceller. Onayli baseline duzenlenmez.
 */
final class ScheduleBaselineService extends AbstractService
{
    protected string $model = ScheduleBaseline::class;

    /** @var list<string> */
    protected array $with = ['baselineRevision', 'approver'];

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
        $projectId = (int) ($data['project_id'] ?? 0);
        $versionNo = (int) ScheduleBaseline::query()->where('project_id', $projectId)->max('version_no') + 1;

        unset($data['status'], $data['approved_by_personnel_id'], $data['approved_at']);

        return parent::create([...$data, 'project_id' => $projectId, 'version_no' => $versionNo, 'status' => BaselineStatus::Draft]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ScheduleBaseline $current */
        $current = $this->show($record);

        if ($current->status !== BaselineStatus::Draft) {
            throw InvalidTransitionException::make(['from' => $current->status->getLabel(), 'to' => '-']);
        }

        unset($data['project_id'], $data['version_no'], $data['status'], $data['approved_by_personnel_id'], $data['approved_at']);

        return parent::update($current, $data);
    }

    public function approve(Model|int|string $record): ScheduleBaseline
    {
        return $this->transactions->run(function () use ($record): ScheduleBaseline {
            /** @var ScheduleBaseline $baseline */
            $baseline = $this->lockForUpdate($record);

            if ($baseline->status !== BaselineStatus::Draft) {
                throw InvalidTransitionException::make(['from' => $baseline->status->getLabel(), 'to' => BaselineStatus::Approved->getLabel()]);
            }

            ScheduleBaseline::query()
                ->where('project_id', $baseline->project_id)
                ->whereKeyNot($baseline->getKey())
                ->where('status', BaselineStatus::Approved->value)
                ->update(['status' => BaselineStatus::Superseded->value]);

            $baseline->forceFill([
                'status' => BaselineStatus::Approved,
                'approved_by_personnel_id' => $this->actor->personnelId(),
                'approved_at' => Carbon::now('UTC'),
            ])->save();

            Project::query()->whereKey($baseline->project_id)->update([
                'planned_start_on' => $baseline->planned_start_on,
                'planned_finish_on' => $baseline->planned_finish_on,
            ]);

            $this->recordActivity($baseline, 'approved', ['surum' => $baseline->version_no]);

            return $baseline;
        });
    }
}
