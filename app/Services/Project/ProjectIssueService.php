<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\IssueStatus;
use App\Models\Project\ProjectIssue;
use App\Models\Project\ProjectWorkstream;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Project\Concerns\NumbersProjectRecords;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje issue servisi (11 SS3.13): ISS-001 numarasi, acan/tarih varsayilani,
 * cozumde resolved_at damgasi.
 */
final class ProjectIssueService extends AbstractService
{
    use ChecksProjectScope, NumbersProjectRecords;

    protected string $model = ProjectIssue::class;

    /** @var list<string> */
    protected array $with = ['workstream.group', 'owner', 'raiser'];

    protected string $orderBy = 'raised_at';

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
        $this->assertSameProject($projectId, ProjectWorkstream::class, $data['workstream_id'] ?? null);

        return parent::create([
            ...$data,
            'issue_no' => $this->nextProjectNumber(ProjectIssue::class, $projectId, 'issue_no', 'ISS'),
            'raised_by_personnel_id' => $data['raised_by_personnel_id'] ?? $this->actor->personnelId(),
            'owner_personnel_id' => $data['owner_personnel_id'] ?? $this->actor->personnelId(),
            'raised_at' => $data['raised_at'] ?? Carbon::now('UTC'),
            'status' => $data['status'] ?? IssueStatus::Open,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ProjectIssue $current */
        $current = $this->show($record);
        unset($data['project_id'], $data['issue_no']);

        if (array_key_exists('workstream_id', $data)) {
            $this->assertSameProject((int) $current->project_id, ProjectWorkstream::class, $data['workstream_id']);
        }

        $status = $data['status'] ?? null;
        $status = $status instanceof IssueStatus ? $status->value : $status;

        if ($status === IssueStatus::Resolved->value && $current->resolved_at === null) {
            $data['resolved_at'] = Carbon::now('UTC');
        }

        return parent::update($current, $data);
    }
}
