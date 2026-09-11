<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\HandoffStatus;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Project\SameProjectRequiredException;
use App\Models\Project\DepartmentHandoff;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\ProjectWorkstream;
use App\Services\AbstractService;
use App\Services\Project\Concerns\ChecksProjectScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Departman devri koku servisi (11 SS3.1, 14 SS2.28 SM-DH). Kaynak/hedef
 * workstream ve tetikleyen gate ayni projeden olmali; kabul yalniz
 * inceleme kaydiyla (DepartmentHandoffReviewService) yazilir.
 */
final class DepartmentHandoffService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = DepartmentHandoff::class;

    /** @var list<string> */
    protected array $with = ['sourceWorkstream.group', 'targetWorkstream.group', 'triggerStageInstance.stageNode', 'acceptedVersion'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $projectId = (int) ($data['project_id'] ?? 0);
        $this->assertSameProject($projectId, ProjectWorkstream::class, $data['source_workstream_id'] ?? null);
        $this->assertSameProject($projectId, ProjectWorkstream::class, $data['target_workstream_id'] ?? null);
        $this->assertSameProject($projectId, ProjectStageInstance::class, $data['trigger_stage_instance_id'] ?? null);

        if ((int) ($data['source_workstream_id'] ?? 0) === (int) ($data['target_workstream_id'] ?? 0)) {
            throw SameProjectRequiredException::make();
        }

        unset($data['status'], $data['accepted_version_id'], $data['accepted_by_personnel_id'], $data['accepted_at']);

        return parent::create([...$data, 'status' => HandoffStatus::Preparing]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return parent::update($record, array_intersect_key($data, array_flip(['sla_due_at', 'row_version'])));
    }

    public function changeStatus(Model|int|string $record, HandoffStatus $target, ?string $reason = null): DepartmentHandoff
    {
        if ($target === HandoffStatus::Accepted) {
            throw InvalidTransitionException::make(['from' => '-', 'to' => $target->getLabel()]);
        }

        return $this->transactions->run(function () use ($record, $target, $reason): DepartmentHandoff {
            /** @var DepartmentHandoff $handoff */
            $handoff = $this->lockForUpdate($record);
            $from = $handoff->status;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $handoff->forceFill(['status' => $target])->save();
            $this->recordActivity($handoff, 'status_changed', ['durum' => ['onceki' => $from->value, 'yeni' => $target->value], 'gerekce' => $reason]);

            return $handoff;
        });
    }
}
