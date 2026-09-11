<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\Personnel\SelfParentNotAllowedException;
use App\Models\Project\WbsNode;
use App\Services\AbstractService;
use App\Services\Project\Concerns\ChecksProjectScope;
use Illuminate\Database\Eloquent\Model;

/**
 * WBS dugumu servisi (11 SS3.5): ust dugum ayni projeden olmali, seviye
 * ust dugumden turetilir, kendi kendine ust olamaz (N-10: servis kurali).
 */
final class WbsNodeService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = WbsNode::class;

    protected string $orderBy = 'wbs_code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return parent::create($this->withLevel((int) ($data['project_id'] ?? 0), $data, null));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var WbsNode $current */
        $current = $this->show($record);
        unset($data['project_id']);

        return parent::update($current, $this->withLevel((int) $current->project_id, $data, (int) $current->getKey()));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withLevel(int $projectId, array $data, ?int $selfId): array
    {
        if (! array_key_exists('parent_id', $data)) {
            return $data;
        }

        $parentId = $data['parent_id'];

        if ($parentId === null || $parentId === '') {
            $data['parent_id'] = null;
            $data['level'] = 1;

            return $data;
        }

        if ($selfId !== null && (int) $parentId === $selfId) {
            throw SelfParentNotAllowedException::make();
        }

        $this->assertSameProject($projectId, WbsNode::class, $parentId);
        $data['level'] = (int) WbsNode::query()->whereKey((int) $parentId)->value('level') + 1;

        return $data;
    }
}
