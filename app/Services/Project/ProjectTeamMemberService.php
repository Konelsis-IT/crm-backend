<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\TeamMemberStatus;
use App\Enums\Project\TeamRole;
use App\Exceptions\DuplicateRecordException;
use App\Models\Project\ProjectTeamMember;
use App\Models\Project\ProjectWorkstream;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje ekibi servisi (11 SS1.12): ayni personel ayni rolde bir kez;
 * uyelik bitince status=ended ve assigned_until damgalanir.
 */
final class ProjectTeamMemberService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = ProjectTeamMember::class;

    /** @var list<string> */
    protected array $with = ['personnel', 'workstream.group'];

    protected string $orderBy = 'assigned_from';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $projectId = (int) ($data['project_id'] ?? 0);
            $this->assertSameProject($projectId, ProjectWorkstream::class, $data['workstream_id'] ?? null);

            $role = $data['team_role'] ?? TeamRole::Other;
            $role = $role instanceof TeamRole ? $role : TeamRole::from((string) $role);

            $exists = ProjectTeamMember::query()
                ->where('project_id', $projectId)
                ->where('personnel_id', (int) ($data['personnel_id'] ?? 0))
                ->where('team_role', $role->value)
                ->exists();

            if ($exists) {
                throw DuplicateRecordException::make();
            }

            return parent::create([
                ...$data,
                'team_role' => $role,
                'assigned_from' => $data['assigned_from'] ?? Carbon::now('UTC')->toDateString(),
                'status' => $data['status'] ?? TeamMemberStatus::Active,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ProjectTeamMember $current */
        $current = $this->show($record);
        unset($data['project_id'], $data['personnel_id']);

        if (array_key_exists('workstream_id', $data)) {
            $this->assertSameProject((int) $current->project_id, ProjectWorkstream::class, $data['workstream_id']);
        }

        $status = $data['status'] ?? null;
        $status = $status instanceof TeamMemberStatus ? $status->value : $status;

        if ($status === TeamMemberStatus::Ended->value && ($data['assigned_until'] ?? $current->assigned_until) === null) {
            $data['assigned_until'] = Carbon::now('UTC')->toDateString();
        }

        return parent::update($current, $data);
    }

    /** Uyeligi bitirir. */
    public function end(Model|int|string $record, ?string $endedOn = null): ProjectTeamMember
    {
        return $this->transactions->run(function () use ($record, $endedOn): ProjectTeamMember {
            /** @var ProjectTeamMember $member */
            $member = $this->lockForUpdate($record);

            if ($member->status === TeamMemberStatus::Ended) {
                return $member;
            }

            $member->forceFill([
                'status' => TeamMemberStatus::Ended,
                'assigned_until' => $endedOn ?? $member->assigned_until?->toDateString() ?? Carbon::now('UTC')->toDateString(),
            ])->save();

            $this->recordActivity($member, 'ended', ['personnel_id' => $member->personnel_id]);

            return $member;
        });
    }
}
