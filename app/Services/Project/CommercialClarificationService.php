<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\ClarificationStatus;
use App\Models\Project\CommercialClarification;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\NumbersProjectRecords;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ticari acikliga kavusturma servisi (11 SS3.18): CLR-001 numarasi,
 * yanitlaninca responded_at damgasi.
 */
final class CommercialClarificationService extends AbstractService
{
    use NumbersProjectRecords;

    protected string $model = CommercialClarification::class;

    /** @var list<string> */
    protected array $with = ['raiser', 'customerContact', 'linkedChange'];

    protected string $orderBy = 'id';

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

        return parent::create([
            ...$data,
            'clarification_no' => $this->nextProjectNumber(CommercialClarification::class, $projectId, 'clarification_no', 'CLR'),
            'raised_by_personnel_id' => $data['raised_by_personnel_id'] ?? $this->actor->personnelId(),
            'status' => $data['status'] ?? ClarificationStatus::Open,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var CommercialClarification $current */
        $current = $this->show($record);
        unset($data['project_id'], $data['clarification_no']);

        if (filled($data['response'] ?? null) && $current->responded_at === null) {
            $data['responded_at'] = Carbon::now('UTC');
            $data['status'] ??= ClarificationStatus::Answered;
        }

        return parent::update($current, $data);
    }
}
