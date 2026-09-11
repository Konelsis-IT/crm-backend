<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Approval\RequestStepStatus;
use App\Models\Approval\ApprovalRequest;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Onay talebi: yonetici/denetci her talebi gorur; talep sahibi ve talebin
 * adimlarinda onayci olan personel kendi talebini gorur. Talep dogrudan
 * olusturulmaz/duzenlenmez; konu ekranindan acilir, kararlar servisle verilir.
 */
final class ApprovalRequestPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, ApprovalRequest $record): bool
    {
        if ($this->canRead($personnel)) {
            return true;
        }

        if ((int) $record->personnel_id === (int) $personnel->getKey()) {
            return true;
        }

        return $record->steps()->where('personnel_id', $personnel->getKey())->exists();
    }

    /** Talep sahibi ya da yonetici acik talebi iptal edebilir. */
    public function cancel(Personnel $personnel, ApprovalRequest $record): bool
    {
        if (! $record->isOpen()) {
            return false;
        }

        return $this->isSystemAdmin($personnel) || (int) $record->personnel_id === (int) $personnel->getKey();
    }

    /** Aktif adimda onayci olan personel karar verebilir (vekalet servisde cozulur). */
    public function decide(Personnel $personnel, ApprovalRequest $record): bool
    {
        if (! $record->isOpen() || ! $personnel->isActive()) {
            return false;
        }

        return $record->steps()
            ->where('status', RequestStepStatus::Active->value)
            ->where('personnel_id', $personnel->getKey())
            ->exists();
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function update(Personnel $personnel, ApprovalRequest $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, ApprovalRequest $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, ApprovalRequest $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, ApprovalRequest $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, ApprovalRequest $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
