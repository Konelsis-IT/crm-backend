<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Approval\Delegation;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Vekalet: yonetici hepsini yonetir; personel kendi verdigi/aldigi vekaleti
 * gorur ve kendi verdigini iptal edebilir.
 */
final class DelegationPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, Delegation $record): bool
    {
        return $this->canRead($personnel) || $this->isParty($personnel, $record);
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function update(Personnel $personnel, Delegation $record): bool
    {
        return $this->hasFullAccess($personnel) || (int) $record->grantor_personnel_id === (int) $personnel->getKey();
    }

    public function delete(Personnel $personnel, Delegation $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, Delegation $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, Delegation $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, Delegation $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    private function isParty(Personnel $personnel, Delegation $record): bool
    {
        $id = (int) $personnel->getKey();

        return (int) $record->grantor_personnel_id === $id || (int) $record->delegate_personnel_id === $id;
    }
}
