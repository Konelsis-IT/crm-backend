<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\ApprovalMode;
use App\Enums\Approval\ApprovalPolicyStatus;
use App\Enums\Approval\PolicyVersionStatus;
use App\Exceptions\Approval\PolicyHasNoStepsException;
use App\Exceptions\Approval\VersionNotDraftException;
use App\Models\Approval\ApprovalPolicy;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Models\Approval\ApprovalStep;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Onay politikasi surumu (12 SS2.2). create: surum no otomatik, taslak;
 * update yalniz taslakta; publish: en az bir adim, tanim hash'i, onceki
 * yayimli surum superseded, politika aktif ve current_version_id guncel.
 */
final class ApprovalPolicyVersionService extends AbstractService
{
    /** @var list<string> */
    private const EDITABLE = [
        'mode', 'quorum_count', 'requires_maker_checker', 'reapproval_on_change', 'applies_min_amount',
        'applies_max_amount', 'currency_code', 'risk_level', 'sla_minutes', 'change_summary', 'row_version',
    ];

    /** @var list<string> */
    protected array $with = ['policy', 'publisher'];

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
        $policyId = (int) ($data['approval_policy_id'] ?? 0);
        $versionNo = (int) ApprovalPolicyVersion::query()->where('approval_policy_id', $policyId)->max('version_no') + 1;

        return parent::create([
            ...array_intersect_key($data, array_flip(self::EDITABLE)),
            'approval_policy_id' => $policyId,
            'version_no' => $versionNo,
            'status' => PolicyVersionStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ApprovalPolicyVersion $current */
        $current = $this->show($record);
        $this->assertDraft($current);

        return parent::update($current, array_intersect_key($data, array_flip(self::EDITABLE)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $data = parent::prepare($data, $record);

        $mode = $data['mode'] ?? $record?->getAttribute('mode');
        $mode = $mode instanceof ApprovalMode ? $mode : ApprovalMode::tryFrom((string) $mode);

        if ($mode !== ApprovalMode::Quorum) {
            $data['quorum_count'] = null;
        }

        foreach (['applies_min_amount', 'applies_max_amount', 'sla_minutes', 'quorum_count'] as $numeric) {
            if (array_key_exists($numeric, $data) && ($data[$numeric] === '' || $data[$numeric] === null)) {
                $data[$numeric] = null;
            }
        }

        return $data;
    }

    public function assertDraft(ApprovalPolicyVersion $version): void
    {
        if (! $version->isDraft()) {
            throw VersionNotDraftException::make(['status' => $version->status->getLabel()]);
        }
    }

    public function publish(Model|int|string $record): ApprovalPolicyVersion
    {
        return $this->transactions->run(function () use ($record): ApprovalPolicyVersion {
            /** @var ApprovalPolicyVersion $version */
            $version = $this->lockForUpdate($record);
            $this->assertDraft($version);

            $steps = $version->steps()->get();

            if ($steps->isEmpty()) {
                throw PolicyHasNoStepsException::make();
            }

            $definition = [
                'mode' => $version->mode->value,
                'quorum' => $version->quorum_count,
                'maker_checker' => $version->requires_maker_checker,
                'reapproval' => $version->reapproval_on_change,
                'amount' => [$version->applies_min_amount, $version->applies_max_amount, $version->currency_code],
                'risk' => $version->risk_level?->value,
                'sla' => $version->sla_minutes,
                'steps' => $steps->map(fn (ApprovalStep $step): array => [
                    $step->step_code, $step->sequence_no, $step->resolver_type->value, $step->resolver_target_id,
                    $step->role_code, $step->decision_rule->value, $step->is_optional, $step->allows_delegation, $step->sla_minutes,
                ])->all(),
            ];

            ApprovalPolicyVersion::query()
                ->where('approval_policy_id', $version->approval_policy_id)
                ->whereKeyNot($version->getKey())
                ->where('status', PolicyVersionStatus::Published->value)
                ->update(['status' => PolicyVersionStatus::Superseded->value]);

            $version->forceFill([
                'status' => PolicyVersionStatus::Published,
                'definition_hash' => hash('sha256', json_encode($definition, JSON_UNESCAPED_UNICODE) ?: ''),
                'published_by_personnel_id' => $this->actor->personnelId(),
                'published_at' => Carbon::now('UTC'),
            ])->save();

            ApprovalPolicy::query()->whereKey($version->approval_policy_id)->update([
                'current_version_id' => $version->getKey(),
                'status' => ApprovalPolicyStatus::Active->value,
            ]);

            $this->recordActivity($version, 'published', ['surum' => $version->version_no]);

            return $version;
        });
    }
}
