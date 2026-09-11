<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\HandoffVersionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\DepartmentHandoffItem;
use App\Models\Project\DepartmentHandoffVersion;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Departman devri maddesi servisi (11 SS3.3): yapi yalniz taslak surumde,
 * tamamlanma durumu taslak ve gonderilmis surumde degisir.
 */
final class DepartmentHandoffItemService extends AbstractService
{
    protected string $model = DepartmentHandoffItem::class;

    protected string $orderBy = 'sort_order';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertVersionStatus((int) ($data['handoff_version_id'] ?? 0), [HandoffVersionStatus::Draft]);
        $data['item_code'] = strtoupper(trim((string) ($data['item_code'] ?? '')));

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var DepartmentHandoffItem $current */
        $current = $this->show($record);
        $structural = array_diff_key($data, array_flip(['completion_state', 'document_revision_id', 'row_version']));

        $this->assertVersionStatus(
            $current->handoff_version_id,
            $structural === [] ? [HandoffVersionStatus::Draft, HandoffVersionStatus::Submitted] : [HandoffVersionStatus::Draft],
        );

        unset($data['handoff_version_id']);

        return parent::update($current, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var DepartmentHandoffItem $current */
        $current = $this->show($record);
        $this->assertVersionStatus($current->handoff_version_id, [HandoffVersionStatus::Draft]);

        return parent::delete($current);
    }

    /**
     * @param  list<HandoffVersionStatus>  $allowed
     */
    private function assertVersionStatus(int $versionId, array $allowed): void
    {
        $status = DepartmentHandoffVersion::query()->whereKey($versionId)->value('status');
        $status = $status instanceof HandoffVersionStatus ? $status : ($status === null ? null : HandoffVersionStatus::from((string) $status));

        if ($status === null || ! in_array($status, $allowed, true)) {
            throw InvalidTransitionException::make(['from' => $status?->getLabel() ?? '-', 'to' => '-']);
        }
    }
}
