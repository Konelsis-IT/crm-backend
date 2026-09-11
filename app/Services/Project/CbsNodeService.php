<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\Personnel\SelfParentNotAllowedException;
use App\Models\Project\CbsNode;
use App\Services\AbstractService;
use App\Services\Project\Concerns\ChecksProjectScope;
use Illuminate\Database\Eloquent\Model;

/**
 * CBS (maliyet kirilimi) dugumu servisi (11 SS3.6): ust dugum ayni
 * projeden olmali, kendi kendine ust olamaz.
 */
final class CbsNodeService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = CbsNode::class;

    protected string $orderBy = 'cost_code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertSameProject((int) ($data['project_id'] ?? 0), CbsNode::class, $data['parent_id'] ?? null);

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var CbsNode $current */
        $current = $this->show($record);
        unset($data['project_id']);

        if (array_key_exists('parent_id', $data) && filled($data['parent_id'])) {
            if ((int) $data['parent_id'] === (int) $current->getKey()) {
                throw SelfParentNotAllowedException::make();
            }

            $this->assertSameProject((int) $current->project_id, CbsNode::class, $data['parent_id']);
        }

        return parent::update($current, $data);
    }
}
