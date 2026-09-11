<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\ChangeStatus;
use App\Models\Project\Project;
use App\Models\Project\ProjectChange;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Project\Concerns\NumbersProjectRecords;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje degisiklik talebi servisi (11 SS3.17): CHG-001 numarasi, talep
 * eden/tarih varsayilani, onayda approved_at. Onay motoru (B07) gelene
 * kadar durum elle yonetilir.
 */
final class ProjectChangeService extends AbstractService
{
    use NumbersProjectRecords;

    protected string $model = ProjectChange::class;

    /** @var list<string> */
    protected array $with = ['requester', 'currency'];

    protected string $orderBy = 'requested_at';

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
            'change_no' => $this->nextProjectNumber(ProjectChange::class, $projectId, 'change_no', 'CHG'),
            'personnel_id' => $data['personnel_id'] ?? $this->actor->personnelId(),
            'requested_at' => $data['requested_at'] ?? Carbon::now('UTC'),
            'currency_code' => $data['currency_code'] ?? Project::query()->whereKey($projectId)->value('currency_code'),
            'status' => $data['status'] ?? ChangeStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ProjectChange $current */
        $current = $this->show($record);
        unset($data['project_id'], $data['change_no']);

        $status = $data['status'] ?? null;
        $status = $status instanceof ChangeStatus ? $status->value : $status;

        if ($status === ChangeStatus::Approved->value && $current->approved_at === null) {
            $data['approved_at'] = Carbon::now('UTC');
        }

        return parent::update($current, $data);
    }
}
