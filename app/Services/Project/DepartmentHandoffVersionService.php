<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\HandoffStatus;
use App\Enums\Acquisition\HandoffVersionStatus;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\DepartmentHandoff;
use App\Models\Project\DepartmentHandoffVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Departman devri surumu servisi (11 SS3.2). create: version_no otomatik,
 * bos manifest; submit: maddeler tamamlanmis olmali, manifest_snapshot
 * maddelerden dondurulur, devir in_review olur, onceki submitted surum
 * superseded.
 */
final class DepartmentHandoffVersionService extends AbstractService
{
    protected string $model = DepartmentHandoffVersion::class;

    /** @var list<string> */
    protected array $with = ['handoff', 'submitter'];

    protected string $orderBy = 'version_no';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly DepartmentHandoffService $handoffs,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var DepartmentHandoff $handoff */
            $handoff = DepartmentHandoff::query()->lockForUpdate()->findOrFail((int) ($data['department_handoff_id'] ?? 0));

            if (! in_array($handoff->status, [HandoffStatus::Preparing, HandoffStatus::Rejected], true)) {
                throw InvalidTransitionException::make(['from' => $handoff->status->getLabel(), 'to' => '-']);
            }

            $versionNo = (int) DepartmentHandoffVersion::query()->where('department_handoff_id', $handoff->getKey())->max('version_no') + 1;
            $manifest = ['items' => [], 'created_at' => Carbon::now('UTC')->toIso8601String()];

            /** @var DepartmentHandoffVersion $version */
            $version = parent::create([
                'department_handoff_id' => $handoff->getKey(),
                'version_no' => $versionNo,
                'manifest_snapshot' => $manifest,
                'snapshot_hash' => hash('sha256', json_encode($manifest) ?: ''),
                'status' => HandoffVersionStatus::Draft,
            ]);

            if ($handoff->status === HandoffStatus::Rejected) {
                $this->handoffs->changeStatus($handoff, HandoffStatus::Preparing);
            }

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        // Surum icerigi maddelerden uretilir; kokte duzenlenecek alan yoktur.
        return $this->show($record);
    }

    public function submit(Model|int|string $record): DepartmentHandoffVersion
    {
        return $this->transactions->run(function () use ($record): DepartmentHandoffVersion {
            /** @var DepartmentHandoffVersion $version */
            $version = $this->lockForUpdate($record);

            if (! $version->status->canTransitionTo(HandoffVersionStatus::Submitted)) {
                throw InvalidTransitionException::make(['from' => $version->status->getLabel(), 'to' => HandoffVersionStatus::Submitted->getLabel()]);
            }

            $items = $version->items()->orderBy('sort_order')->get();
            $pending = $items->filter(fn ($item): bool => ! in_array($item->completion_state, [CompletionState::Complete, CompletionState::Waived, CompletionState::NotApplicable], true))->count();

            if ($pending > 0) {
                throw GuardNotSatisfiedException::make(['reason' => "{$pending} devir maddesi hala bekliyor"]);
            }

            $manifest = [
                'items' => $items->map(fn ($item): array => [
                    'code' => $item->item_code,
                    'type' => $item->item_type->value,
                    'state' => $item->completion_state->value,
                    'document_revision_id' => $item->document_revision_id,
                ])->all(),
                'submitted_at' => Carbon::now('UTC')->toIso8601String(),
            ];

            DepartmentHandoffVersion::query()
                ->where('department_handoff_id', $version->department_handoff_id)
                ->whereKeyNot($version->getKey())
                ->where('status', HandoffVersionStatus::Submitted->value)
                ->update(['status' => HandoffVersionStatus::Superseded->value]);

            $version->forceFill([
                'manifest_snapshot' => $manifest,
                'snapshot_hash' => hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE) ?: ''),
                'status' => HandoffVersionStatus::Submitted,
                'submitted_by_personnel_id' => $this->actor->personnelId(),
                'submitted_at' => Carbon::now('UTC'),
            ])->save();

            $this->recordActivity($version, 'submitted', ['surum' => $version->version_no]);
            $this->handoffs->changeStatus($version->department_handoff_id, HandoffStatus::InReview);

            return $version;
        });
    }
}
