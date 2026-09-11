<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\HandoffVersionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\HandoffItem;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Devir maddesi servisi (10 SS5.3). Madde yapisi yalniz taslak surumde
 * degisir; tamamlanma durumu taslak ve gonderilmis surumde guncellenebilir,
 * muafiyette muaf tutan kisi yazilir.
 */
final class HandoffItemService extends AbstractService
{
    protected string $model = HandoffItem::class;

    protected string $orderBy = 'sort_order';

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
        $this->assertVersionStatus((int) ($data['handoff_version_id'] ?? 0), [HandoffVersionStatus::Draft]);

        return parent::create($this->stampWaiver($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var HandoffItem $current */
        $current = $this->show($record);
        $structural = array_diff_key($data, array_flip(['completion_state', 'document_revision_id', 'row_version']));

        $this->assertVersionStatus(
            $current->handoff_version_id,
            $structural === [] ? [HandoffVersionStatus::Draft, HandoffVersionStatus::Submitted] : [HandoffVersionStatus::Draft],
        );

        unset($data['handoff_version_id']);

        return parent::update($current, $this->stampWaiver($data));
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var HandoffItem $current */
        $current = $this->show($record);
        $this->assertVersionStatus($current->handoff_version_id, [HandoffVersionStatus::Draft]);

        return parent::delete($current);
    }

    /**
     * @param  list<HandoffVersionStatus>  $allowed
     */
    private function assertVersionStatus(int $versionId, array $allowed): void
    {
        $status = OperationHandoffVersion::query()->whereKey($versionId)->value('status');
        $status = $status instanceof HandoffVersionStatus ? $status : ($status === null ? null : HandoffVersionStatus::from((string) $status));

        if ($status === null || ! in_array($status, $allowed, true)) {
            throw InvalidTransitionException::make(['from' => $status?->getLabel() ?? '-', 'to' => '-']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampWaiver(array $data): array
    {
        $state = $data['completion_state'] ?? null;
        $state = $state instanceof CompletionState ? $state->value : $state;

        if ($state === CompletionState::Waived->value) {
            $data['waived_by_personnel_id'] = $this->actor->personnelId();
        } elseif ($state !== null) {
            $data['waived_by_personnel_id'] = null;
        }

        return $data;
    }
}
