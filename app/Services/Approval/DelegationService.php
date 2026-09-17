<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\DelegationScopeType;
use App\Enums\Approval\DelegationStatus;
use App\Exceptions\Approval\SelfDelegationException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Approval\Delegation;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Authorization\RoleResolver;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Vekalet (06 SS4.11, SM-DELEG). Vekalet veren varsayilan olarak islemi
 * yapan kisidir; kendine vekalet verilemez; simdilik vekalet ayri bir onay
 * beklemeden "aktif" acilir (onay adimi sonraki turda). revoke: iptal.
 */
final class DelegationService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['grantor', 'delegate'];

    protected string $orderBy = 'valid_until';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly RoleResolver $roles,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $data = parent::prepare($data, $record);

        if ($record === null) {
            $actorId = $this->actor->personnelId();
            $data['grantor_personnel_id'] = (int) ($data['grantor_personnel_id'] ?? $actorId ?? 0);
            $data['capability_code'] = $data['capability_code'] ?? Delegation::CAPABILITY_APPROVAL_DECIDE;
            $data['status'] = DelegationStatus::Active;

            /** @var Personnel|null $actor */
            $actor = $actorId === null ? null : Personnel::query()->find($actorId);
            $data['approved_by_personnel_id'] = $actor !== null && $this->roles->hasFullAccess($actor) ? $actorId : null;
        }

        $grantor = (int) ($data['grantor_personnel_id'] ?? $record?->getAttribute('grantor_personnel_id') ?? 0);
        $delegate = (int) ($data['delegate_personnel_id'] ?? $record?->getAttribute('delegate_personnel_id') ?? 0);

        if ($grantor !== 0 && $grantor === $delegate) {
            throw SelfDelegationException::make();
        }

        $scope = $data['scope_type'] ?? $record?->getAttribute('scope_type');
        $scope = $scope instanceof DelegationScopeType ? $scope : DelegationScopeType::tryFrom((string) $scope);

        if ($scope === DelegationScopeType::All) {
            $data['scope_id'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var Delegation $delegation */
        $delegation = $this->show($record);

        if (! in_array($delegation->status, [DelegationStatus::Pending, DelegationStatus::Active], true)) {
            throw InvalidTransitionException::make(['from' => $delegation->status->getLabel(), 'to' => '-']);
        }

        unset($data['grantor_personnel_id'], $data['status'], $data['capability_code']);

        return parent::update($delegation, $data);
    }

    public function revoke(Model|int|string $record, ?string $reason = null): Delegation
    {
        return $this->transactions->run(function () use ($record, $reason): Delegation {
            /** @var Delegation $delegation */
            $delegation = $this->lockForUpdate($record);

            if (! in_array($delegation->status, [DelegationStatus::Pending, DelegationStatus::Active], true)) {
                throw InvalidTransitionException::make(['from' => $delegation->status->getLabel(), 'to' => DelegationStatus::Revoked->getLabel()]);
            }

            $delegation->forceFill([
                'status' => DelegationStatus::Revoked,
                'revoked_at' => Carbon::now('UTC'),
                'revoked_by_personnel_id' => $this->actor->personnelId(),
                'revoke_reason' => $reason,
            ])->save();

            $this->recordActivity($delegation, 'revoked', ['gerekce' => $reason]);

            return $delegation;
        });
    }
}
