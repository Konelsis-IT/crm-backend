<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\StageNode;
use App\Models\Project\StageTemplateVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Gate tanimi servisi (11 SS2.3): yalniz taslak sablon surumunde degisir.
 */
final class StageNodeService extends AbstractService
{
    protected string $model = StageNode::class;

    protected string $orderBy = 'sequence_no';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly StageTemplateVersionService $versions,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->versions->assertDraft(StageTemplateVersion::query()->findOrFail((int) ($data['stage_template_version_id'] ?? 0)));
        $data['stage_code'] = strtoupper(trim((string) ($data['stage_code'] ?? '')));

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var StageNode $current */
        $current = $this->show($record);
        $this->versions->assertDraft($current->templateVersion);
        unset($data['stage_template_version_id']);

        return parent::update($current, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var StageNode $current */
        $current = $this->show($record);
        $this->versions->assertDraft($current->templateVersion);

        return parent::delete($current);
    }
}
