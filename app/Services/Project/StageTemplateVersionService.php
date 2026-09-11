<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Project\StageTemplateStatus;
use App\Enums\Project\StageTemplateVersionStatus;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Project\StageTemplate;
use App\Models\Project\StageTemplateVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stage-gate sablon surumu servisi (11 SS2.2).
 *
 * create: version_no otomatik, taslak. publish: en az bir node olmali;
 * definition_hash node/bagimlilik/gereksinim tanimindan hesaplanir,
 * onceki yayimli surum superseded olur, sablonun current_version_id ve
 * durumu (active) guncellenir. Yayimlanan surum icerik olarak degismez.
 */
final class StageTemplateVersionService extends AbstractService
{
    protected string $model = StageTemplateVersion::class;

    /** @var list<string> */
    protected array $with = ['template', 'publisher'];

    protected string $orderBy = 'version_no';

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
        $templateId = (int) ($data['stage_template_id'] ?? 0);
        $versionNo = (int) StageTemplateVersion::query()->where('stage_template_id', $templateId)->max('version_no') + 1;

        return parent::create([
            'stage_template_id' => $templateId,
            'version_no' => $versionNo,
            'status' => StageTemplateVersionStatus::Draft,
            'change_summary' => $data['change_summary'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var StageTemplateVersion $current */
        $current = $this->show($record);
        $this->assertDraft($current);

        return parent::update($current, array_intersect_key($data, array_flip(['change_summary', 'row_version'])));
    }

    public function assertDraft(StageTemplateVersion $version): void
    {
        if ($version->status !== StageTemplateVersionStatus::Draft) {
            throw InvalidTransitionException::make(['from' => $version->status->getLabel(), 'to' => '-']);
        }
    }

    public function publish(Model|int|string $record): StageTemplateVersion
    {
        return $this->transactions->run(function () use ($record): StageTemplateVersion {
            /** @var StageTemplateVersion $version */
            $version = $this->lockForUpdate($record);
            $this->assertDraft($version);

            $nodes = $version->nodes()->with(['requirementDefinitions', 'successorDependencies'])->orderBy('sequence_no')->get();

            if ($nodes->isEmpty()) {
                throw GuardNotSatisfiedException::make(['reason' => 'sablon surumunde hic gate yok']);
            }

            $definition = $nodes->map(fn ($node): array => [
                'code' => $node->stage_code,
                'seq' => $node->sequence_no,
                'hard' => $node->is_hard_gate,
                'requirements' => $node->requirementDefinitions->map(fn ($r): array => [$r->requirement_code, $r->evidence_type->value, $r->is_mandatory])->all(),
                'successors' => $node->successorDependencies->map(fn ($d): array => [$d->successor_node_id, $d->is_hard])->all(),
            ])->all();

            StageTemplateVersion::query()
                ->where('stage_template_id', $version->stage_template_id)
                ->whereKeyNot($version->getKey())
                ->where('status', StageTemplateVersionStatus::Published->value)
                ->update(['status' => StageTemplateVersionStatus::Superseded->value]);

            $version->forceFill([
                'status' => StageTemplateVersionStatus::Published,
                'definition_hash' => hash('sha256', json_encode($definition, JSON_UNESCAPED_UNICODE) ?: ''),
                'published_by_personnel_id' => $this->actor->personnelId(),
                'published_at' => Carbon::now('UTC'),
            ])->save();

            StageTemplate::query()->whereKey($version->stage_template_id)->update([
                'current_version_id' => $version->getKey(),
                'status' => StageTemplateStatus::Active->value,
            ]);

            $this->recordActivity($version, 'published', ['surum' => $version->version_no]);

            return $version;
        });
    }
}
