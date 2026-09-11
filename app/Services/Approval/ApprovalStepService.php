<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\ResolverType;
use App\Exceptions\Approval\VersionNotDraftException;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Models\Approval\ApprovalStep;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Politika surumundeki adimlar (12 SS2.3). Yalniz taslak surumde eklenir,
 * duzenlenir ve silinir; yayimli surumun adimlari degismez.
 */
final class ApprovalStepService extends AbstractService
{
    protected string $orderBy = 'sequence_no';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $versionId = (int) ($data['approval_policy_version_id'] ?? 0);
        $this->assertVersionDraft($versionId);

        if (blank($data['sequence_no'] ?? null)) {
            $data['sequence_no'] = (int) ApprovalStep::query()->where('approval_policy_version_id', $versionId)->max('sequence_no') + 1;
        }

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ApprovalStep $step */
        $step = $this->show($record);
        $this->assertVersionDraft((int) $step->approval_policy_version_id);
        unset($data['approval_policy_version_id']);

        return parent::update($step, $data);
    }

    public function delete(Model|int|string $record): bool
    {
        /** @var ApprovalStep $step */
        $step = $this->show($record);
        $this->assertVersionDraft((int) $step->approval_policy_version_id);

        return parent::delete($step);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $data = parent::prepare($data, $record);

        if (isset($data['step_code'])) {
            $data['step_code'] = strtoupper(trim((string) $data['step_code']));
        }

        $type = $data['resolver_type'] ?? $record?->getAttribute('resolver_type');
        $type = $type instanceof ResolverType ? $type : ResolverType::tryFrom((string) $type);

        if ($type !== null && ! $type->needsTarget()) {
            $data['resolver_target_id'] = null;
        }

        if ($type !== null && ! $type->needsRoleCode()) {
            $data['role_code'] = null;
        }

        if (array_key_exists('sla_minutes', $data) && ($data['sla_minutes'] === '' || $data['sla_minutes'] === null)) {
            $data['sla_minutes'] = null;
        }

        return $data;
    }

    private function assertVersionDraft(int $versionId): void
    {
        /** @var ApprovalPolicyVersion|null $version */
        $version = ApprovalPolicyVersion::query()->find($versionId);

        if ($version === null || ! $version->isDraft()) {
            throw VersionNotDraftException::make(['status' => $version?->status->getLabel() ?? '-']);
        }
    }
}
