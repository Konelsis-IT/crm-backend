<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\ApprovalPolicyStatus;
use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Approval\ApprovalPolicy;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Onay politikasi koku (12 SS2.1). Kod tekil ve buyuk harflidir; konu turu
 * yayimli surum varken degistirilemez (surumler o tur icin yazilmistir).
 */
final class ApprovalPolicyService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['currentVersion'];

    protected string $orderBy = 'code';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $data = parent::prepare($data, $record);

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));

            $duplicate = ApprovalPolicy::query()
                ->where('code', $data['code'])
                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($duplicate) {
                throw CodeAlreadyInUseException::make(['code' => $data['code']]);
            }
        }

        if ($record === null) {
            $data['status'] = $data['status'] ?? ApprovalPolicyStatus::Draft;
        }

        if ($record !== null && $record->getAttribute('current_version_id') !== null) {
            unset($data['subject_type']);
        }

        return $data;
    }

    public function retire(Model|int|string $record): ApprovalPolicy
    {
        return $this->transactions->run(function () use ($record): ApprovalPolicy {
            /** @var ApprovalPolicy $policy */
            $policy = $this->lockForUpdate($record);
            $policy->forceFill(['status' => ApprovalPolicyStatus::Retired])->save();
            $this->recordActivity($policy, 'status_changed', ['durum' => ['onceki' => 'active', 'yeni' => 'retired']]);

            return $policy;
        });
    }
}
