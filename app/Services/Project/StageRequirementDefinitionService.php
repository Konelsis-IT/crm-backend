<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project\StageNode;
use App\Models\Project\StageRequirementDefinition;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Gate gereksinim tanimi servisi (11 SS2.5): yalniz taslak sablon
 * surumunde degisir.
 */
final class StageRequirementDefinitionService extends AbstractService
{
    protected string $model = StageRequirementDefinition::class;

    protected string $orderBy = 'sort_order';

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
        $node = StageNode::query()->findOrFail((int) ($data['stage_node_id'] ?? 0));
        $this->versions->assertDraft($node->templateVersion);
        $data['requirement_code'] = strtoupper(trim((string) ($data['requirement_code'] ?? '')));

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var StageRequirementDefinition $current */
        $current = $this->show($record);
        $this->versions->assertDraft($current->node->templateVersion);
        unset($data['stage_node_id']);

        return parent::update($current, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var StageRequirementDefinition $current */
        $current = $this->show($record);
        $this->versions->assertDraft($current->node->templateVersion);

        return parent::delete($current);
    }
}
