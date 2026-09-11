<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\SupplyItemStatus;
use App\Models\Project\Project;
use App\Models\Project\ProjectSupplyItem;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\WbsNode;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Project\Concerns\ChecksProjectScope;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tedarik kalemi servisi (11 SS1.11): satin alma adiminda acilir,
 * lojistik adiminda teslim durumu, saha adiminda montaj durumu islenir.
 * Para birimi projeden, tarih damgalari durumdan turetilir.
 */
final class ProjectSupplyItemService extends AbstractService
{
    use ChecksProjectScope;

    protected string $model = ProjectSupplyItem::class;

    /** @var list<string> */
    protected array $with = ['workstream.group', 'uom', 'supplier', 'wbsNode'];

    protected string $orderBy = 'id';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $projectId = (int) ($data['project_id'] ?? 0);
        $this->assertSameProject($projectId, ProjectWorkstream::class, $data['workstream_id'] ?? null);
        $this->assertSameProject($projectId, WbsNode::class, $data['wbs_node_id'] ?? null);

        $data['currency_code'] = $data['currency_code'] ?? Project::query()->whereKey($projectId)->value('currency_code');
        $data['status'] = $data['status'] ?? SupplyItemStatus::Planned;

        return parent::create($this->stampDates($data, null));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ProjectSupplyItem $current */
        $current = $this->show($record);
        unset($data['project_id']);

        if (array_key_exists('workstream_id', $data)) {
            $this->assertSameProject((int) $current->project_id, ProjectWorkstream::class, $data['workstream_id']);
        }

        if (array_key_exists('wbs_node_id', $data)) {
            $this->assertSameProject((int) $current->project_id, WbsNode::class, $data['wbs_node_id']);
        }

        return parent::update($current, $this->stampDates($data, $current));
    }

    /** Durum degisimi; siparis ve teslim tarihleri otomatik damgalanir. */
    public function changeStatus(Model|int|string $record, SupplyItemStatus $target): ProjectSupplyItem
    {
        return $this->transactions->run(function () use ($record, $target): ProjectSupplyItem {
            /** @var ProjectSupplyItem $item */
            $item = $this->lockForUpdate($record);
            $from = $item->status;

            if ($from === $target) {
                return $item;
            }

            $item->forceFill($this->stampDates(['status' => $target], $item))->save();
            $this->recordActivity($item, 'status_changed', ['durum' => ['onceki' => $from->value, 'yeni' => $target->value]]);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampDates(array $data, ?ProjectSupplyItem $current): array
    {
        $status = $data['status'] ?? null;
        $status = $status instanceof SupplyItemStatus ? $status : ($status === null ? null : SupplyItemStatus::from((string) $status));

        if ($status === null) {
            return $data;
        }

        $today = Carbon::now('UTC')->toDateString();

        if (in_array($status, SupplyItemStatus::orderedStates(), true) && ($data['ordered_on'] ?? $current?->ordered_on) === null) {
            $data['ordered_on'] = $today;
        }

        if (in_array($status, SupplyItemStatus::deliveredStates(), true) && ($data['delivered_on'] ?? $current?->delivered_on) === null) {
            $data['delivered_on'] = $today;
        }

        return $data;
    }
}
