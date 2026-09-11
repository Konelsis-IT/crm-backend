<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\StageRequirementStatus;
use App\Models\Document\DocumentRevision;
use App\Models\Project\ProjectStageRequirement;
use App\Models\Project\StageEvidence;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gate kaniti servisi (11 SS2.8): kanit, DMS'teki tam bir doküman
 * revizyonudur; hash'i revizyonun content_hash'inden alinir. Kanit
 * sunulunca gereksinim 'submitted', kabul edilince 'accepted' olur.
 */
final class StageEvidenceService extends AbstractService
{
    protected string $model = StageEvidence::class;

    /** @var list<string> */
    protected array $with = ['documentRevision.document', 'submitter', 'acceptor'];

    protected string $orderBy = 'submitted_at';

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
            /** @var DocumentRevision $revision */
            $revision = DocumentRevision::query()->findOrFail((int) ($data['document_revision_id'] ?? 0));
            $requirementId = (int) ($data['project_stage_requirement_id'] ?? 0);

            /** @var StageEvidence $evidence */
            $evidence = parent::create([
                'project_stage_requirement_id' => $requirementId,
                'document_revision_id' => $revision->getKey(),
                'evidence_hash' => $revision->content_hash,
                'submitted_by_personnel_id' => $this->actor->personnelId() ?? $revision->prepared_by_personnel_id,
                'submitted_at' => Carbon::now('UTC'),
            ]);

            ProjectStageRequirement::query()
                ->whereKey($requirementId)
                ->whereIn('status', [StageRequirementStatus::Pending->value, StageRequirementStatus::Rejected->value])
                ->update(['status' => StageRequirementStatus::Submitted->value]);

            return $evidence;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        // Kanit satiri icerik olarak degismez; yalniz kabul islemi vardir.
        return $this->show($record);
    }

    public function accept(Model|int|string $record): StageEvidence
    {
        return $this->transactions->run(function () use ($record): StageEvidence {
            /** @var StageEvidence $evidence */
            $evidence = $this->lockForUpdate($record);

            $evidence->forceFill([
                'accepted_by_personnel_id' => $this->actor->personnelId(),
                'accepted_at' => Carbon::now('UTC'),
            ])->save();

            ProjectStageRequirement::query()
                ->whereKey($evidence->project_stage_requirement_id)
                ->update(['status' => StageRequirementStatus::Accepted->value]);

            $this->recordActivity($evidence, 'accepted');

            return $evidence;
        });
    }
}
